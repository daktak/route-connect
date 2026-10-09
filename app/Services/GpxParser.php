<?php

namespace App\Services;

class GpxParser
{
    private const DEFAULT_NAMESPACE = 'http://www.topografix.com/GPX/1/1';

    public function parse(string $gpxContent): array
    {
        $xml = simplexml_load_string($gpxContent);

        if (! $xml) {
            throw new \InvalidArgumentException('Invalid GPX file');
        }

        // Register namespaces (support both GPX 1.0 and 1.1 default namespaces)
        $namespaces = $xml->getNamespaces(true);
        $namespace = $namespaces[''] ?? $namespaces['gpx'] ?? self::DEFAULT_NAMESPACE;

        $tracks = [];
        $waypoints = [];
        $bounds = ['min_lat' => 90, 'max_lat' => -90, 'min_lng' => 180, 'max_lng' => -180];

        // Parse tracks
        foreach ($this->xpath($xml, 'trk', $namespace) as $trk) {
            $trackName = (string) ($trk->name ?? 'Track');
            $segments = [];

            foreach ($this->xpath($trk, 'trkseg', $namespace) as $trkseg) {
                $points = [];

                foreach ($this->xpath($trkseg, 'trkpt', $namespace) as $trkpt) {
                    $lat = (float) $trkpt['lat'];
                    $lon = (float) $trkpt['lon'];
                    $ele = (float) ($trkpt->ele ?? 0);
                    $time = (string) ($trkpt->time ?? '');

                    $points[] = [$lon, $lat, $ele];

                    // Update bounds
                    $bounds['min_lat'] = min($bounds['min_lat'], $lat);
                    $bounds['max_lat'] = max($bounds['max_lat'], $lat);
                    $bounds['min_lng'] = min($bounds['min_lng'], $lon);
                    $bounds['max_lng'] = max($bounds['max_lng'], $lon);
                }

                if (! empty($points)) {
                    $segments[] = $points;
                }
            }

            if (! empty($segments)) {
                $tracks[] = [
                    'name' => $trackName,
                    'segments' => $segments,
                    'points_count' => array_sum(array_map('count', $segments)),
                ];
            }
        }

        // Parse waypoints
        foreach ($this->xpath($xml, 'wpt', $namespace) as $wpt) {
            $lat = (float) $wpt['lat'];
            $lon = (float) $wpt['lon'];
            $name = (string) ($wpt->name ?? '');
            $sym = (string) ($wpt->sym ?? '');

            $waypoints[] = [
                'lat' => $lat,
                'lon' => $lon,
                'name' => $name,
                'sym' => $sym,
            ];
        }

        // Flatten all points for calculations
        $allPoints = [];
        foreach ($tracks as $track) {
            foreach ($track['segments'] as $segment) {
                $allPoints = array_merge($allPoints, $segment);
            }
        }

        // Compute distance
        $distanceKm = $this->calculateDistance($allPoints);

        // Compute elevation gain
        $elevationGain = $this->calculateElevationGain($allPoints);

        // Estimate time (avg 20 km/h on flat, adjust for elevation)
        $estimatedTimeMin = $this->estimateTime($distanceKm, $elevationGain);

        // Determine difficulty
        $difficulty = $this->determineDifficulty($distanceKm, $elevationGain);

        // Build geometry (LineString)
        $geometry = $this->buildGeometry($allPoints);

        // Build GPX data for storage
        $gpxData = [
            'tracks' => $tracks,
            'waypoints' => $waypoints,
            'bounds' => $bounds,
            'points_count' => count($allPoints),
        ];

        return [
            'gpx_data' => $gpxData,
            'geometry' => $geometry,
            'distance_km' => round($distanceKm, 2),
            'elevation_gain_m' => $elevationGain,
            'estimated_time_min' => $estimatedTimeMin,
            'difficulty' => $difficulty,
            'name' => $tracks[0]['name'] ?? 'Untitled Route',
        ];
    }

    public function crop(array $parsed, int $startPct, int $endPct): array
    {
        $points = $this->flattenPoints($parsed);

        $count = count($points);
        if ($count <= 2 || $startPct + $endPct >= 100) {
            return $parsed;
        }

        $startIndex = (int) round($count * $startPct / 100);
        $endIndex = (int) round($count * $endPct / 100);

        if ($count - $startIndex - $endIndex < 2) {
            $endIndex = $count - $startIndex - 2;
        }
        if ($endIndex < 0) {
            return $parsed;
        }

        $cropped = array_slice($points, $startIndex, $count - $startIndex - $endIndex);

        $distanceKm = $this->calculateDistance($cropped);
        $elevationGain = $this->calculateElevationGain($cropped);

        $gpxData = $parsed['gpx_data'] ?? [];
        $name = $gpxData['tracks'][0]['name'] ?? 'Track';

        $gpxData['tracks'] = [[
            'name' => $name,
            'segments' => [$cropped],
            'points_count' => count($cropped),
        ]];
        $gpxData['points_count'] = count($cropped);
        $gpxData['bounds'] = $this->computeBounds($cropped);

        return [
            'gpx_data' => $gpxData,
            'geometry' => $this->buildGeometry($cropped),
            'distance_km' => round($distanceKm, 2),
            'elevation_gain_m' => $elevationGain,
            'estimated_time_min' => $this->estimateTime($distanceKm, $elevationGain),
            'difficulty' => $this->determineDifficulty($distanceKm, $elevationGain),
            'name' => $parsed['name'] ?? $name,
        ];
    }

    private function flattenPoints(array $parsed): array
    {
        $points = [];

        foreach (($parsed['gpx_data']['tracks'] ?? []) as $track) {
            foreach (($track['segments'] ?? []) as $segment) {
                foreach ($segment as $point) {
                    $points[] = [$point[0], $point[1], $point[2] ?? 0];
                }
            }
        }

        return $points;
    }

    private function computeBounds(array $points): array
    {
        $bounds = ['min_lat' => 90, 'max_lat' => -90, 'min_lng' => 180, 'max_lng' => -180];

        foreach ($points as $point) {
            $lat = (float) $point[1];
            $lng = (float) $point[0];
            $bounds['min_lat'] = min($bounds['min_lat'], $lat);
            $bounds['max_lat'] = max($bounds['max_lat'], $lat);
            $bounds['min_lng'] = min($bounds['min_lng'], $lng);
            $bounds['max_lng'] = max($bounds['max_lng'], $lng);
        }

        return $bounds;
    }

    private function xpath(\SimpleXMLElement $element, string $path, string $namespace): array
    {
        $element->registerXPathNamespace('gpx', $namespace);

        return $element->xpath("gpx:{$path}") ?: [];
    }

    private function calculateDistance(array $points): float
    {
        $total = 0;
        for ($i = 1; $i < count($points); $i++) {
            $total += $this->haversineDistance(
                $points[$i - 1][1], $points[$i - 1][0],
                $points[$i][1], $points[$i][0]
            );
        }

        return $total;
    }

    private function calculateElevationGain(array $points): int
    {
        $gain = 0;
        for ($i = 1; $i < count($points); $i++) {
            $diff = $points[$i][2] - $points[$i - 1][2];
            if ($diff > 0) {
                $gain += $diff;
            }
        }

        return (int) round($gain);
    }

    private function estimateTime(float $distanceKm, int $elevationGainM): int
    {
        // Base speed: 20 km/h on flat
        // Add 1 minute per 10m elevation gain (Naismith's rule approximation)
        $baseTimeHours = $distanceKm / 20;
        $elevationTimeHours = $elevationGainM / 600; // 10m/min = 600m/h
        $totalHours = $baseTimeHours + $elevationTimeHours;

        return (int) round($totalHours * 60);
    }

    private function determineDifficulty(float $distanceKm, int $elevationGainM): string
    {
        // Simple difficulty calculation
        $score = ($distanceKm / 10) + ($elevationGainM / 200);

        if ($score < 3) {
            return 'easy';
        }
        if ($score < 6) {
            return 'moderate';
        }
        if ($score < 10) {
            return 'hard';
        }

        return 'expert';
    }

    private function buildGeometry(array $points): ?array
    {
        if (empty($points)) {
            return null;
        }

        $coordinates = array_map(fn ($p) => [$p[0], $p[1]], $points);

        return [
            'type' => 'LineString',
            'coordinates' => $coordinates,
        ];
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
