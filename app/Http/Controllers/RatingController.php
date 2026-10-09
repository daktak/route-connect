<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RouteRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    public function store(Request $request, Route $route)
    {
        $request->validate([
            'rating' => 'required|integer|between:1,5',
        ]);

        $rating = RouteRating::updateOrCreate(
            ['route_id' => $route->id, 'user_id' => Auth::id()],
            ['rating' => $request->rating]
        );

        // Refresh materialized view is handled by trigger

        return response()->json([
            'success' => true,
            'rating' => $rating->rating,
            'avg_rating' => $route->fresh()->avg_rating,
            'rating_count' => $route->fresh()->rating_count,
        ]);
    }
}
