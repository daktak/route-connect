<?php

namespace App\Services;

use App\Models\GroupRide;
use App\Services\Concerns\HandlesOpenMeteoData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AirQualityService
{
    use HandlesOpenMeteoData;

    private const HOURLY = [
        'european_aqi',
        'us_aqi',
        'pm2_5',
        'pm10',
    ];

    /**
     * Resolve air quality for each ride, batched into a single API request.
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

            $result[$ride->id] = $forecast ? $this->airQualityForRide($ride, $forecast) : null;
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

        return $forecast ? $this->airQualityForRide($ride, $forecast) : null;
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
                config('services.open_meteo.air_quality_url'),
                [
                    'latitude' => implode(',', $lats),
                    'longitude' => implode(',', $lngs),
                    'hourly' => implode(',', self::HOURLY),
                    'timezone' => 'auto',
                    'timeformat' => 'unixtime',
                    'forecast_days' => min(7, (int) config('services.open_meteo.air_quality_forecast_days', 5)),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Open-Meteo air quality request failed', ['message' => $e->getMessage()]);

            return $result;
        }

        if (! $response->successful()) {
            Log::warning('Open-Meteo air quality returned an error', ['status' => $response->status()]);

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

            $cached = ['hourly' => $entry['hourly']];
            Cache::put($key, $cached, config('services.open_meteo.air_quality_cache_ttl', 1800));
            $result[$key] = $cached;
        }

        return $result;
    }

    /**
     * @param  array{hourly: array<string, array<int, mixed>>}  $data
     * @return array<string, mixed>|null
     */
    private function airQualityForRide(GroupRide $ride, array $data): ?array
    {
        $hourly = $data['hourly'] ?? [];
        $times = $hourly['time'] ?? [];

        if (empty($times)) {
            return null;
        }

        $target = $ride->ride_date->getTimestamp();
        $index = $this->closestIndex($times, $target);

        if ($index === null || abs((int) $times[$index] - $target) > 5400) {
            return null;
        }

        $us = $hourly['us_aqi'][$index] ?? null;
        $european = $hourly['european_aqi'][$index] ?? null;

        if ($us !== null) {
            $aqi = (int) round((float) $us);
            $scale = 'us';
        } elseif ($european !== null) {
            $aqi = (int) round((float) $european);
            $scale = 'european';
        } else {
            return null;
        }

        return [
            'aqi' => $aqi,
            'scale' => $scale,
            'level' => $this->aqiLabel($aqi, $scale),
            'pm2_5' => $this->round($hourly['pm2_5'][$index] ?? null),
            'pm10' => $this->round($hourly['pm10'][$index] ?? null),
            'time' => Carbon::createFromTimestamp((int) $times[$index], 'UTC'),
        ];
    }

    private function aqiLabel(int $aqi, string $scale): string
    {
        if ($scale === 'us') {
            return match (true) {
                $aqi <= 50 => 'Good',
                $aqi <= 100 => 'Moderate',
                $aqi <= 150 => 'Unhealthy for sensitive groups',
                $aqi <= 200 => 'Unhealthy',
                $aqi <= 300 => 'Very unhealthy',
                default => 'Hazardous',
            };
        }

        return match (true) {
            $aqi <= 20 => 'Good',
            $aqi <= 40 => 'Fair',
            $aqi <= 60 => 'Moderate',
            $aqi <= 80 => 'Poor',
            $aqi <= 100 => 'Very poor',
            default => 'Extremely poor',
        };
    }

    private function cacheKey(float $lat, float $lng): string
    {
        return 'air-quality:'.round($lat, 3).':'.round($lng, 3).':'.now('UTC')->format('Y-m-d-H');
    }
}
