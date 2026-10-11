<?php

namespace App\Services\Concerns;

use App\Models\GroupRide;

trait HandlesOpenMeteoData
{
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
}
