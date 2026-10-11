<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoutePreferenceController extends Controller
{
    public const FILTER_KEYS = [
        'search',
        'difficulty',
        'minDistance',
        'maxDistance',
        'minElevation',
        'maxElevation',
        'features',
        'sort',
    ];

    public function updateFilters(Request $request)
    {
        $data = $request->validate([
            'filters' => ['nullable', 'array'],
            'filters.search' => ['nullable', 'string', 'max:255'],
            'filters.difficulty' => ['nullable', Rule::in(['', 'easy', 'moderate', 'hard', 'expert'])],
            'filters.minDistance' => ['nullable', 'numeric', 'min:0'],
            'filters.maxDistance' => ['nullable', 'numeric', 'min:0'],
            'filters.minElevation' => ['nullable', 'numeric', 'min:0'],
            'filters.maxElevation' => ['nullable', 'numeric', 'min:0'],
            'filters.features' => ['nullable', 'array'],
            'filters.features.*' => ['string'],
            'filters.sort' => ['nullable', Rule::in(['newest', 'oldest', 'distance_asc', 'distance_desc', 'elevation_asc', 'elevation_desc', 'rating_desc', 'popular'])],
        ]);

        $filters = collect($data['filters'] ?? [])
            ->only(self::FILTER_KEYS)
            ->reject(fn ($value) => $value === null || $value === '' || $value === [])
            ->all();

        $request->user()->update(['route_filters' => $filters ?: null]);

        return response()->noContent();
    }

    public function updateViewMode(Request $request)
    {
        $data = $request->validate([
            'view_mode' => ['required', Rule::in(['card', 'list'])],
        ]);

        $request->user()->update(['route_view_mode' => $data['view_mode']]);

        return response()->noContent();
    }
}
