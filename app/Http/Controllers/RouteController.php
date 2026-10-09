<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Services\GpxParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RouteController extends Controller
{
    public function index(Request $request)
    {
        $query = Route::public()
            ->with(['user', 'features', 'avgRating', 'upcomingRides'])
            ->withCount(['ratings', 'comments']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Difficulty
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        // Distance range
        if ($request->filled('minDistance')) {
            $query->where('distance_km', '>=', $request->minDistance);
        }
        if ($request->filled('maxDistance')) {
            $query->where('distance_km', '<=', $request->maxDistance);
        }

        // Elevation range
        if ($request->filled('minElevation')) {
            $query->where('elevation_gain_m', '>=', $request->minElevation);
        }
        if ($request->filled('maxElevation')) {
            $query->where('elevation_gain_m', '<=', $request->maxElevation);
        }

        // Features
        if ($request->filled('features')) {
            $features = is_array($request->features) ? $request->features : explode(',', $request->features);
            $query->whereHas('features', fn ($q) => $q->whereIn('feature_type', $features));
        }

        // Sorting
        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'distance_asc' => $query->orderBy('distance_km', 'asc'),
            'distance_desc' => $query->orderBy('distance_km', 'desc'),
            'elevation_asc' => $query->orderBy('elevation_gain_m', 'asc'),
            'elevation_desc' => $query->orderBy('elevation_gain_m', 'desc'),
            'rating_desc' => $query->join('route_avg_ratings as rav', 'routes.id', '=', 'rav.route_id')
                ->orderBy('rav.avg_rating', 'desc')
                ->select('routes.*'),
            'popular' => $query->join('route_avg_ratings as rav', 'routes.id', '=', 'rav.route_id')
                ->orderBy('rav.rating_count', 'desc')
                ->select('routes.*'),
            default => $query->latest(),
        };

        $routes = $query->paginate(15)->withQueryString();

        return view('routes.index', compact('routes'));
    }

    public function map(Request $request)
    {
        $query = Route::public()->whereNotNull('geometry');

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }
        if ($request->filled('features')) {
            $features = is_array($request->features) ? $request->features : explode(',', $request->features);
            $query->whereHas('features', fn ($q) => $q->whereIn('feature_type', $features));
        }

        $routes = $query->with('features')
            ->leftJoin('route_avg_ratings as rav', 'routes.id', '=', 'rav.route_id')
            ->select([
                'routes.id', 'routes.name', 'routes.description', 'routes.distance_km',
                'routes.elevation_gain_m', 'routes.difficulty',
                DB::raw('ST_AsGeoJSON(routes.geometry) as geometry'),
                'rav.avg_rating', 'rav.rating_count',
            ])
            ->limit(200)
            ->get();

        // Prepare map data for Alpine.js
        $mapRoutes = $routes->map(function ($r) {
            return [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'geometry' => $r->geometry,
                'distance_km' => $r->distance_km,
                'elevation_gain_m' => $r->elevation_gain_m,
                'difficulty' => $r->difficulty,
                'avg_rating' => $r->avg_rating,
                'rating_count' => $r->rating_count,
                'features' => $r->features->map(function ($f) {
                    return [
                        'id' => $f->id,
                        'feature_type' => $f->feature_type,
                        'label' => $f->label,
                        'icon' => $f->icon,
                    ];
                })->toArray(),
                'upcoming_rides' => $r->upcomingRides->take(1)->map(function ($ride) {
                    return ['id' => $ride->id];
                })->toArray(),
            ];
        })->toArray();

        return view('routes.map', [
            'routes' => $routes,
            'mapRoutes' => $mapRoutes,
        ]);
    }

    public function create()
    {
        return view('routes.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'gpx_file' => 'required|file|max:10240|extensions:gpx',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'difficulty' => ['nullable', Rule::in(['easy', 'moderate', 'hard', 'expert'])],
            'estimated_time_min' => 'nullable|integer|min:1',
            'is_public' => 'boolean',
            'features' => 'nullable|array',
            'features.*' => 'string',
            'custom_feature' => 'nullable|string',
            'start_crop' => 'nullable|integer|min:0|max:90',
            'end_crop' => 'nullable|integer|min:0|max:90',
        ]);

        $gpxFile = $request->file('gpx_file');
        $gpxContent = file_get_contents($gpxFile->getRealPath());

        // Parse GPX
        $parser = new GpxParser;

        try {
            $parsed = $parser->parse($gpxContent);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'gpx_file' => 'The uploaded file is not a valid GPX file.',
            ]);
        }

        $startCrop = (int) $request->start_crop;
        $endCrop = (int) $request->end_crop;

        if (($startCrop > 0 || $endCrop > 0) && $startCrop + $endCrop < 100) {
            $parsed = $parser->crop($parsed, $startCrop, $endCrop);
        }

        // Store GPX file
        $path = $gpxFile->store('gpx', 'local');

        $route = DB::transaction(function () use ($request, $parsed, $path) {
            $route = Route::create([
                'user_id' => Auth::id(),
                'name' => $request->name,
                'description' => $request->description,
                'gpx_data' => $parsed['gpx_data'],
                'distance_km' => $parsed['distance_km'],
                'elevation_gain_m' => $parsed['elevation_gain_m'],
                'estimated_time_min' => $request->estimated_time_min ?? $parsed['estimated_time_min'],
                'difficulty' => $request->difficulty ?? $parsed['difficulty'] ?? 'moderate',
                'is_public' => $request->boolean('is_public', true),
                'gpx_file_path' => $path,
            ]);

            $this->storeGeometry($route, $parsed['geometry']);

            // Handle features
            $this->syncFeatures($route, $request->features ?? []);

            return $route;
        });

        return redirect()->route('routes.show', $route)
            ->with('success', 'Route created successfully!');
    }

    public function show(Route $route)
    {
        $route->load([
            'user',
            'features',
            'avgRating',
            'ratings' => fn ($q) => $q->where('user_id', Auth::id()),
            'comments.user',
            'comments.replies.user',
            'upcomingRides.organizer',
            'upcomingRides.attendees.user',
        ]);

        // Generate elevation profile data
        $elevationProfile = $this->generateElevationProfile($route->gpx_data);

        $featuresData = $route->features->map(fn ($f) => [
            'id' => $f->id,
            'feature_type' => $f->feature_type,
            'description' => $f->description,
            'start_lat' => $f->start_lat,
            'start_lng' => $f->start_lng,
            'label' => $f->label,
            'icon' => $f->icon,
        ])->values()->all();

        $geometry = $route->geometry;

        return view('routes.show', compact('route', 'elevationProfile', 'featuresData', 'geometry'));
    }

    public function edit(Route $route)
    {
        $this->authorize('update', $route);

        return view('routes.edit', compact('route'));
    }

    public function update(Request $request, Route $route)
    {
        $this->authorize('update', $route);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'difficulty' => ['nullable', Rule::in(['easy', 'moderate', 'hard', 'expert'])],
            'estimated_time_min' => 'nullable|integer|min:1',
            'is_public' => 'boolean',
            'features' => 'nullable|array',
            'features.*' => 'string',
            'custom_feature' => 'nullable|string',
        ]);

        $route->update([
            'name' => $request->name,
            'description' => $request->description,
            'difficulty' => $request->difficulty,
            'estimated_time_min' => $request->estimated_time_min,
            'is_public' => $request->boolean('is_public', true),
        ]);

        $this->syncFeatures($route, $request->features ?? []);

        return redirect()->route('routes.show', $route)
            ->with('success', 'Route updated successfully!');
    }

    public function destroy(Route $route)
    {
        $this->authorize('delete', $route);
        $route->delete();

        return redirect()->route('routes.index')
            ->with('success', 'Route deleted successfully!');
    }

    public function download(Route $route)
    {
        if (! $route->gpx_file_path || ! Storage::disk('local')->exists($route->gpx_file_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($route->gpx_file_path, "{$route->name}.gpx");
    }

    private function storeGeometry(Route $route, ?array $geometry): void
    {
        if (empty($geometry)) {
            return;
        }

        DB::update(
            'UPDATE routes SET geometry = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) WHERE id = ?',
            [json_encode($geometry), $route->id]
        );
    }

    private function syncFeatures(Route $route, array $features)
    {
        // Delete existing features not in new list
        $existingTypes = collect($features)
            ->filter(fn ($f) => ! str_starts_with($f, 'custom:'))
            ->toArray();
        $route->features()->whereNotIn('feature_type', $existingTypes)->delete();

        // Sync standard features
        $standardFeatures = array_filter($features, fn ($f) => ! str_starts_with($f, 'custom:'));
        foreach ($standardFeatures as $type) {
            $route->features()->updateOrCreate(
                ['feature_type' => $type],
                ['sort_order' => array_search($type, $standardFeatures)]
            );
        }

        // Handle custom features
        $customFeatures = array_filter($features, fn ($f) => str_starts_with($f, 'custom:'));
        foreach ($customFeatures as $feature) {
            $subtype = str_replace('custom:', '', $feature);
            $route->features()->updateOrCreate(
                ['feature_type' => 'custom', 'feature_subtype' => $subtype],
                ['sort_order' => 999]
            );
        }
    }

    private function generateElevationProfile(array $gpxData): array
    {
        $segments = $gpxData['tracks'][0]['segments'] ?? [];
        $profile = [];

        foreach ($segments as $segment) {
            foreach ($segment as $point) {
                $profile[] = [
                    'lat' => $point[1] ?? 0,
                    'lng' => $point[0] ?? 0,
                    'elevation' => $point[2] ?? 0,
                ];
            }
        }

        // Downsample to ~200 points for chart
        if (count($profile) > 200) {
            $step = ceil(count($profile) / 200);
            $profile = array_values(array_filter($profile, fn ($_, $i) => $i % $step === 0, ARRAY_FILTER_USE_BOTH));
        }

        // Add cumulative distance
        $totalDistance = 0;
        foreach ($profile as $i => &$point) {
            if ($i > 0) {
                $prev = $profile[$i - 1];
                $totalDistance += $this->haversineDistance(
                    $prev['lat'], $prev['lng'],
                    $point['lat'], $point['lng']
                );
            }
            $point['distance_km'] = round($totalDistance, 2);
        }

        return array_values($profile);
    }

    private function haversineDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
