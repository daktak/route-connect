<?php

namespace App\Services;

use App\Models\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SurfaceAnalyzer
{
    /**
     * OSM/Valhalla surface values treated as sealed (paved) riding.
     * Anything else that is tagged is treated as gravel/unpaved.
     */
    private const PAVED_SURFACES = [
        'paved',
        'paved_smooth',
        'asphalt',
        'concrete',
        'concrete:plates',
        'concrete:lanes',
        'paving_stones',
        'sett',
        'unhewn_cobblestone',
        'cobblestone',
        'cobblestone:flattened',
        'bricks',
        'brick',
        'metal',
        'steel',
        'wood',
        'timber',
        'tar',
        'chipseal',
        'acrylic',
        'tiles',
    ];

    /**
     * Maximum number of points sent to Valhalla. Longer tracks are sampled.
     */
    private const MAX_POINTS = 4000;

    public function updateRoute(Route $route): bool
    {
        $result = $this->analyze($route);

        if ($result === null) {
            return false;
        }

        $route->forceFill([
            'surface_breakdown' => $result['breakdown'],
            'gravel_pct' => $result['gravel_pct'],
            'surface_analyzed_at' => now(),
            'surface_source' => $result['source'],
        ])->save();

        return true;
    }

    /**
     * @return array{breakdown: array<string, float>, gravel_pct: float, source: string}|null
     */
    public function analyze(Route $route): ?array
    {
        $shape = $this->buildShape($route);

        if (count($shape) < 2) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('services.valhalla.timeout', 30))
                ->acceptJson()
                ->post(rtrim((string) config('services.valhalla.base_url'), '/').'/trace_attributes', [
                    'shape' => $shape,
                    'costing' => 'bicycle',
                    'shape_match' => 'map_snap',
                    'search_radius' => 50,
                    'filters' => [
                        'attributes' => ['edge.surface', 'edge.length'],
                        'action' => 'include',
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Valhalla surface request failed', [
                'route_id' => $route->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Valhalla surface request returned an error', [
                'route_id' => $route->id,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return null;
        }

        $edges = $response->json('edges');

        if (! is_array($edges) || $edges === []) {
            Log::warning('Valhalla surface response contained no edges', ['route_id' => $route->id]);

            return null;
        }

        $lengths = ['paved' => 0.0, 'gravel' => 0.0, 'unknown' => 0.0];

        foreach ($edges as $edge) {
            $length = (float) ($edge['length'] ?? 0);

            if ($length <= 0) {
                continue;
            }

            $lengths[$this->classifySurface($edge['surface'] ?? null)] += $length;
        }

        $total = array_sum($lengths);

        if ($total <= 0) {
            return null;
        }

        $breakdown = [];
        foreach ($lengths as $category => $length) {
            $breakdown[$category] = round($length / $total * 100, 1);
        }

        return [
            'breakdown' => $breakdown,
            'gravel_pct' => $breakdown['gravel'],
            'source' => 'valhalla',
        ];
    }

    public function classifySurface(?string $surface): string
    {
        if ($surface === null || trim($surface) === '') {
            return 'unknown';
        }

        $surface = strtolower(trim($surface));

        // Valhalla uses its own "paved"/"paved_smooth" values alongside OSM surfaces.
        if ($surface === 'paved' || str_starts_with($surface, 'paved:') || str_starts_with($surface, 'paved_')) {
            return 'paved';
        }

        foreach (self::PAVED_SURFACES as $paved) {
            if ($surface === $paved || str_starts_with($surface, $paved.':')) {
                return 'paved';
            }
        }

        return 'gravel';
    }

    /**
     * @return array<int, array{lat: float, lon: float}>
     */
    private function buildShape(Route $route): array
    {
        $coordinates = $route->geometry['coordinates'] ?? [];

        if (count($coordinates) < 2) {
            return [];
        }

        $coordinates = $this->downsample($coordinates, self::MAX_POINTS);

        $shape = [];
        foreach ($coordinates as $point) {
            if (! isset($point[0], $point[1])) {
                continue;
            }

            $shape[] = ['lat' => (float) $point[1], 'lon' => (float) $point[0]];
        }

        return $shape;
    }

    /**
     * @param  array<int, array<int, float>>  $coordinates
     * @return array<int, array<int, float>>
     */
    private function downsample(array $coordinates, int $max): array
    {
        $coordinates = array_values($coordinates);
        $count = count($coordinates);

        if ($count <= $max) {
            return $coordinates;
        }

        $stride = (int) ceil($count / $max);
        $sampled = [];

        for ($i = 0; $i < $count; $i += $stride) {
            $sampled[] = $coordinates[$i];
        }

        $last = $coordinates[$count - 1];
        if ($sampled[count($sampled) - 1] !== $last) {
            $sampled[] = $last;
        }

        return $sampled;
    }
}
