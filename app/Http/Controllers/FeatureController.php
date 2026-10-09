<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RouteFeature;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    public function store(Request $request, Route $route)
    {
        $this->authorize('update', $route);

        $request->validate([
            'feature_type' => 'required|string|max:50',
            'feature_subtype' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'start_lat' => 'nullable|numeric|between:-90,90',
            'start_lng' => 'nullable|numeric|between:-180,180',
            'end_lat' => 'nullable|numeric|between:-90,90',
            'end_lng' => 'nullable|numeric|between:-180,180',
        ]);

        $feature = RouteFeature::create([
            'route_id' => $route->id,
            'feature_type' => $request->feature_type,
            'feature_subtype' => $request->feature_subtype,
            'description' => $request->description,
            'start_lat' => $request->start_lat,
            'start_lng' => $request->start_lng,
            'end_lat' => $request->end_lat,
            'end_lng' => $request->end_lng,
            'sort_order' => $route->features()->max('sort_order') + 1,
        ]);

        return response()->json(['success' => true, 'feature' => $feature]);
    }

    public function destroy(RouteFeature $feature)
    {
        $this->authorize('update', $feature->route);
        $feature->delete();

        return response()->json(['success' => true]);
    }
}
