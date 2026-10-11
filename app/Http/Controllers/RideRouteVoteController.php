<?php

namespace App\Http\Controllers;

use App\Models\GroupRide;
use App\Models\RideRouteVote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RideRouteVoteController extends Controller
{
    public function store(Request $request, GroupRide $ride)
    {
        $request->validate([
            'route_id' => 'required|integer|exists:routes,id',
        ]);

        if (! $ride->hasVoting()) {
            return response()->json([
                'success' => false,
                'message' => 'Voting is not available for this ride.',
            ], 422);
        }

        $attendee = $ride->attendees()
            ->where('user_id', Auth::id())
            ->where('ride_version', $ride->version)
            ->first();

        if (! $attendee || $attendee->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'Only confirmed attendees can vote.',
            ], 403);
        }

        if (! $ride->routeOptions()->where('route_id', $request->route_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'That route is not an option for this ride.',
            ], 422);
        }

        RideRouteVote::updateOrCreate(
            [
                'group_ride_id' => $ride->id,
                'user_id' => Auth::id(),
                'ride_version' => $ride->version,
            ],
            ['route_id' => $request->route_id]
        );

        return response()->json([
            'success' => true,
            'route_id' => (int) $request->route_id,
        ]);
    }
}
