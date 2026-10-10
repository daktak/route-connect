<?php

namespace App\Services;

use App\Models\GroupRide;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private const HOURLY = [
        'temperature_2m',
        'apparent_temperature',
        'precipitation_probability',
        'weather_code',
        'wind_speed_10m',
        'wind_gusts_10m',
    ];

    private const DAILY = [
        'sunrise',
        'sunset',
    ];

    /**
     * Resolve a forecast for each ride, batched into a single API request.
     *
     * @param  Collection<int, GroupRide>  $rides
     * @return array<int, array<string, mixed>|null>
     */
    public function forRides(Collection $rides): array
    {
        $coordsByRide = [];
        $locations = [];

        foreach ($rides as $ride) {
            $coords = $this->resolveCoordinates($ride);
            $coordsByRide[$ride->id] = $coords;

            if ($coords) {
                $locations[$this->cacheKey($coords['lat'], $coords['lng'])] = $coords;
            }
        }

        $forecasts = $this->fetchLocations($locations);

        $result = [];
        foreach ($rides as $ride) {
            $coords = $coordsByRide[$ride->id] ?? null;
            $forecast = $coords
                ? ($forecasts[$this->cacheKey($coords['lat'], $coords['lng'])] ?? null)
                : null;

            $result[$ride->id] = $forecast ? $this->forecastForRide($ride, $forecast) : null;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forRide(GroupRide $ride): ?array
    {
        $coords = $this->resolveCoordinates($ride);
        if (! $coords) {
            return null;
        }

        $key = $this->cacheKey($coords['lat'], $coords['lng']);
        $forecasts = $this->fetchLocations([$key => $coords]);
        $forecast = $forecasts[$key] ?? null;

        return $forecast ? $this->forecastForRide($ride, $forecast) : null;
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $locations
     * @return array<string, array<string, array<int, mixed>>>
     */
    private function fetchLocations(array $locations): array
    {
        if (empty($locations)) {
            return [];
        }

        $result = [];
        $misses = [];

        foreach ($locations as $key => $coords) {
            $cached = Cache::get($key);
            if ($cached !== null) {
                $result[$key] = $cached;
            } else {
                $misses[$key] = $coords;
            }
        }

        if (empty($misses)) {
            return $result;
        }

        $lats = [];
        $lngs = [];
        foreach ($misses as $coords) {
            $lats[] = round($coords['lat'], 4);
            $lngs[] = round($coords['lng'], 4);
        }

        try {
            $response = Http::timeout(5)->retry(1, 200)->get(
                config('services.open_meteo.base_url'),
                [
                    'latitude' => implode(',', $lats),
                    'longitude' => implode(',', $lngs),
                    'hourly' => implode(',', self::HOURLY),
                    'daily' => implode(',', self::DAILY),
                    'timezone' => 'auto',
                    'timeformat' => 'unixtime',
                    'forecast_days' => config('services.open_meteo.forecast_days', 16),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Open-Meteo request failed', ['message' => $e->getMessage()]);

            return $result;
        }

        if (! $response->successful()) {
            Log::warning('Open-Meteo returned an error', ['status' => $response->status()]);

            return $result;
        }

        $data = $response->json();
        $entries = array_is_list($data) ? $data : [$data];

        $index = 0;
        foreach ($misses as $key => $coords) {
            $entry = $entries[$index] ?? null;
            $index++;

            if (! is_array($entry) || empty($entry['hourly']['time'])) {
                continue;
            }

            $cached = [
                'hourly' => $entry['hourly'],
                'daily' => $entry['daily'] ?? [],
            ];
            Cache::put($key, $cached, config('services.open_meteo.cache_ttl', 1800));
            $result[$key] = $cached;
        }

        return $result;
    }

    /**
     * @param  array{hourly: array<string, array<int, mixed>>, daily: array<string, array<int, string>>}  $data
     * @return array<string, mixed>|null
     */
    private function forecastForRide(GroupRide $ride, array $data): ?array
    {
        $hourly = $data['hourly'] ?? [];
        $daily = $data['daily'] ?? [];

        $times = $hourly['time'] ?? [];
        if (empty($times)) {
            return null;
        }

        $target = $ride->ride_date->getTimestamp();
        $index = $this->closestIndex($times, $target);

        if ($index === null || abs((int) $times[$index] - $target) > 5400) {
            return null;
        }

        $code = (int) ($hourly['weather_code'][$index] ?? 0);

        // Extract sunrise/sunset for the ride date from daily data
        $sunrise = null;
        $sunset = null;
        $dailyTimes = $daily['time'] ?? [];
        if (! empty($dailyTimes)) {
            $rideTimestamp = $ride->ride_date->startOfDay()->getTimestamp();
            $dayIndex = $this->closestIndex($dailyTimes, $rideTimestamp);
            if ($dayIndex !== null && isset($daily['sunrise'][$dayIndex], $daily['sunset'][$dayIndex])) {
                $sunrise = Carbon::createFromTimestamp((int) $daily['sunrise'][$dayIndex], 'UTC');
                $sunset = Carbon::createFromTimestamp((int) $daily['sunset'][$dayIndex], 'UTC');
            }
        }

        return [
            'temperature' => $this->round($hourly['temperature_2m'][$index] ?? null),
            'apparent' => $this->round($hourly['apparent_temperature'][$index] ?? null),
            'precip_probability' => $this->round($hourly['precipitation_probability'][$index] ?? null),
            'wind_speed' => $this->round($hourly['wind_speed_10m'][$index] ?? null),
            'wind_gusts' => $this->round($hourly['wind_gusts_10m'][$index] ?? null),
            'weather_code' => $code,
            'condition' => $this->conditionLabel($code),
            'icon' => $this->conditionIcon($code),
            'time' => Carbon::createFromTimestamp((int) $times[$index], 'UTC'),
            'sunrise' => $sunrise,
            'sunset' => $sunset,
        ];
    }

    /**
     * @param  array<int, int|string>  $times
     */
    private function closestIndex(array $times, int $target): ?int
    {
        $closest = null;
        $closestDiff = null;

        foreach ($times as $index => $time) {
            $diff = abs((int) $time - $target);
            if ($closestDiff === null || $diff < $closestDiff) {
                $closest = $index;
                $closestDiff = $diff;
            }
        }

        return $closest;
    }

    private function round(int|float|string|null $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function resolveCoordinates(GroupRide $ride): ?array
    {
        if ($ride->meeting_point_lat && $ride->meeting_point_lng) {
            return [
                'lat' => (float) $ride->meeting_point_lat,
                'lng' => (float) $ride->meeting_point_lng,
            ];
        }

        $route = $ride->route;
        if (! $route) {
            return null;
        }

        $lat = $route->start_lat ?? null;
        $lng = $route->start_lng ?? null;

        if (! $lat || ! $lng) {
            $geometry = $route->geometry ?? [];
            $coordinates = $geometry['coordinates'] ?? [];
            $start = $coordinates[0] ?? null;
            $lat = $start[1] ?? null;
            $lng = $start[0] ?? null;
        }

        if (! $lat || ! $lng) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }

    private function cacheKey(float $lat, float $lng): string
    {
        return 'weather:'.round($lat, 3).':'.round($lng, 3).':'.now('UTC')->format('Y-m-d-H');
    }

    private function conditionLabel(int $code): string
    {
        return match (true) {
            $code === 0 => 'Clear sky',
            $code === 1 => 'Mainly clear',
            $code === 2 => 'Partly cloudy',
            $code === 3 => 'Overcast',
            in_array($code, [45, 48], true) => 'Fog',
            in_array($code, [51, 53, 55, 56, 57], true) => 'Drizzle',
            in_array($code, [61, 63, 65, 66, 67], true) => 'Rain',
            in_array($code, [71, 73, 75, 77], true) => 'Snow',
            in_array($code, [80, 81, 82], true) => 'Rain showers',
            in_array($code, [85, 86], true) => 'Snow showers',
            in_array($code, [95], true) => 'Thunderstorm',
            in_array($code, [96, 99], true) => 'Thunderstorm with hail',
            default => 'Unsettled',
        };
    }

    private function conditionIcon(int $code): string
    {
        return match (true) {
            $code === 0 => '☀️',
            in_array($code, [1, 2], true) => '🌤️',
            $code === 3 => '☁️',
            in_array($code, [45, 48], true) => '🌫️',
            in_array($code, [51, 53, 55, 56, 57], true) => '🌦️',
            in_array($code, [61, 63, 65, 66, 67], true) => '🌧️',
            in_array($code, [71, 73, 75, 77], true) => '❄️',
            in_array($code, [80, 81, 82], true) => '🌧️',
            in_array($code, [85, 86], true) => '🌨️',
            in_array($code, [95, 96, 99], true) => '⛈️',
            default => '🌡️',
        };
    }
}
