<?php

namespace App\Services;

use App\Models\GroupRide;
use App\Services\Concerns\HandlesOpenMeteoData;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TimezoneService
{
    use HandlesOpenMeteoData;

    /**
     * Resolve the IANA timezone for a ride, derived from its coordinates.
     */
    public function forRide(GroupRide $ride): ?string
    {
        $coords = $this->resolveCoordinates($ride);

        if (! $coords) {
            return null;
        }

        return $this->forCoordinates($coords['lat'], $coords['lng']);
    }

    /**
     * Resolve the IANA timezone for a latitude/longitude pair via Open-Meteo,
     * caching the result for a month. Returns null when unknown or unreachable.
     */
    public function forCoordinates(float $lat, float $lng): ?string
    {
        $lat = round($lat, 4);
        $lng = round($lng, 4);

        $key = 'tz:'.$lat.':'.$lng;

        return Cache::remember($key, now()->addMonth(), function () use ($lat, $lng) {
            try {
                $response = Http::timeout(5)->retry(1, 200)->get(
                    config('services.open_meteo.base_url'),
                    [
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'current' => 'temperature_2m',
                        'timezone' => 'auto',
                        'forecast_days' => 1,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Open-Meteo timezone lookup failed', ['message' => $e->getMessage()]);

                return null;
            }

            if (! $response->successful()) {
                Log::warning('Open-Meteo timezone lookup returned an error', ['status' => $response->status()]);

                return null;
            }

            $timezone = $response->json('timezone');

            if (! is_string($timezone) || ! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
                return null;
            }

            return $timezone;
        });
    }
}
