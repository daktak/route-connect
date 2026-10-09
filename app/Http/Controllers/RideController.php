<?php

namespace App\Http\Controllers;

use App\Models\GroupRide;
use App\Models\RideAttendee;
use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RideController extends Controller
{
    public function index(Request $request)
    {
        $upcomingRides = GroupRide::upcoming()
            ->with(['route', 'organizer', 'attendees.user'])
            ->withCount('attendees')
            ->paginate(15, ['*'], 'upcoming');

        $pastRides = GroupRide::past()
            ->with(['route', 'organizer', 'attendees.user'])
            ->withCount('attendees')
            ->paginate(15, ['*'], 'past');

        return view('rides.index', compact('upcomingRides', 'pastRides'));
    }

    public function create()
    {
        $routes = Route::public()
            ->orderBy('name')
            ->get(['id', 'name', 'distance_km', 'elevation_gain_m', 'difficulty']);

        return view('rides.create', compact('routes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'route_id' => 'required|exists:routes,id',
            'title' => 'nullable|string|max:255',
            'ride_date' => 'required|date|after:now',
            'meeting_point_name' => 'nullable|string|max:255',
            'meeting_point_lat' => 'nullable|numeric|between:-90,90',
            'meeting_point_lng' => 'nullable|numeric|between:-180,180',
            'max_participants' => 'nullable|integer|min:2',
            'description' => 'nullable|string',
        ]);

        $ride = GroupRide::create([
            'route_id' => $request->route_id,
            'organizer_id' => Auth::id(),
            'title' => $request->title,
            'ride_date' => $request->ride_date,
            'meeting_point_name' => $request->meeting_point_name,
            'meeting_point_lat' => $request->meeting_point_lat,
            'meeting_point_lng' => $request->meeting_point_lng,
            'max_participants' => $request->max_participants,
            'description' => $request->description,
            'status' => 'planned',
            'version' => 1,
        ]);

        // Organizer auto-joins
        RideAttendee::create([
            'group_ride_id' => $ride->id,
            'user_id' => Auth::id(),
            'ride_version' => 1,
            'status' => 'confirmed',
        ]);

        return redirect()->route('rides.show', $ride)
            ->with('success', 'Group ride created! You are automatically attending.');
    }

    public function show(GroupRide $ride)
    {
        $ride->load([
            'route.features',
            'organizer',
            'attendees.user',
            'attendees' => fn ($q) => $q->where('ride_version', $ride->version),
        ]);

        return view('rides.show', compact('ride'));
    }

    public function edit(GroupRide $ride)
    {
        $this->authorize('update', $ride);

        $routes = Route::public()->orderBy('name')->get(['id', 'name', 'distance_km', 'elevation_gain_m', 'difficulty']);

        return view('rides.edit', compact('ride', 'routes'));
    }

    public function update(Request $request, GroupRide $ride)
    {
        $this->authorize('update', $ride);

        $request->validate([
            'route_id' => 'required|exists:routes,id',
            'title' => 'nullable|string|max:255',
            'ride_date' => 'required|date',
            'meeting_point_name' => 'nullable|string|max:255',
            'meeting_point_lat' => 'nullable|numeric|between:-90,90',
            'meeting_point_lng' => 'nullable|numeric|between:-180,180',
            'max_participants' => 'nullable|integer|min:2',
            'description' => 'nullable|string',
            'status' => ['required', Rule::in(['planned', 'confirmed', 'cancelled', 'completed'])],
        ]);

        $criticalChanged = $ride->route_id !== $request->route_id
            || $ride->ride_date->ne($request->ride_date);

        DB::transaction(function () use ($request, $ride, $criticalChanged) {
            $ride->update([
                'route_id' => $request->route_id,
                'title' => $request->title,
                'ride_date' => $request->ride_date,
                'meeting_point_name' => $request->meeting_point_name,
                'meeting_point_lat' => $request->meeting_point_lat,
                'meeting_point_lng' => $request->meeting_point_lng,
                'max_participants' => $request->max_participants,
                'description' => $request->description,
                'status' => $request->status,
                'version' => $criticalChanged ? $ride->version + 1 : $ride->version,
            ]);

            if ($criticalChanged) {
                // Notify removed attendees
                $removed = $ride->attendees()
                    ->where('ride_version', '<', $ride->version)
                    ->with('user')
                    ->get();

                foreach ($removed as $attendee) {
                    // Notification logic here
                }
            }
        });

        return redirect()->route('rides.show', $ride)
            ->with('success', 'Ride updated successfully!');
    }

    public function destroy(GroupRide $ride)
    {
        $this->authorize('delete', $ride);
        $ride->delete();

        return redirect()->route('rides.index')
            ->with('success', 'Ride cancelled!');
    }
}
