<?php

namespace App\Http\Controllers;

use App\Events\RideJoined;
use App\Events\RideLeft;
use App\Models\GroupRide;
use App\Models\RideAttendee;
use App\Models\RideRouteVote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RideAttendeeController extends Controller
{
    public function join(Request $request, GroupRide $ride)
    {
        $ride->load('attendees');

        // Check if ride is full
        if ($ride->max_participants && $ride->confirmedAttendees()->where('ride_version', $ride->version)->count() >= $ride->max_participants) {
            return response()->json([
                'success' => false,
                'message' => 'This ride is full.',
            ], 422);
        }

        // Check if already attending current version
        $existing = $ride->attendees()
            ->where('user_id', Auth::id())
            ->where('ride_version', $ride->version)
            ->first();

        if ($existing) {
            // Toggle status
            $statuses = ['confirmed', 'tentative', 'declined'];
            $currentIndex = array_search($existing->status, $statuses);
            $nextStatus = $statuses[($currentIndex + 1) % count($statuses)];

            $existing->update(['status' => $nextStatus]);

            // A vote only stands while the attendee is confirmed.
            if ($nextStatus !== 'confirmed') {
                RideRouteVote::where('group_ride_id', $ride->id)
                    ->where('user_id', Auth::id())
                    ->where('ride_version', $ride->version)
                    ->delete();
            }

            return response()->json([
                'success' => true,
                'status' => $nextStatus,
                'ride_version' => $ride->version,
            ]);
        }

        // Join for first time
        $attendee = RideAttendee::create([
            'group_ride_id' => $ride->id,
            'user_id' => Auth::id(),
            'ride_version' => $ride->version,
            'status' => 'confirmed',
        ]);

        // Notify organizer + Discord
        event(new RideJoined($ride, Auth::user()));

        return response()->json([
            'success' => true,
            'status' => 'confirmed',
            'ride_version' => $ride->version,
        ]);
    }

    public function leave(Request $request, GroupRide $ride)
    {
        $attendee = $ride->attendees()
            ->where('user_id', Auth::id())
            ->where('ride_version', $ride->version)
            ->first();

        if (! $attendee) {
            return response()->json([
                'success' => false,
                'message' => 'You are not attending this ride.',
            ], 422);
        }

        $attendee->delete();

        RideRouteVote::where('group_ride_id', $ride->id)
            ->where('user_id', Auth::id())
            ->where('ride_version', $ride->version)
            ->delete();

        event(new RideLeft($ride, Auth::user()));

        return response()->json(['success' => true]);
    }

    public function status(Request $request, GroupRide $ride)
    {
        $attendee = $ride->attendees()
            ->where('user_id', Auth::id())
            ->where('ride_version', $ride->version)
            ->first();

        return response()->json([
            'status' => $attendee?->status,
            'ride_version' => $ride->version,
        ]);
    }
}
