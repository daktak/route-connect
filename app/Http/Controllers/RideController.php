<?php

namespace App\Http\Controllers;

use App\Models\GroupRide;
use App\Models\RideAttendee;
use App\Models\Route;
use App\Notifications\RideCancelled;
use App\Notifications\RideChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RideController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'upcoming');
        if (! in_array($status, ['upcoming', 'past', 'all'], true)) {
            $status = 'upcoming';
        }

        $query = GroupRide::with(['route', 'organizer', 'attendees.user'])
            ->withCount('attendees');

        if ($status === 'upcoming') {
            $query->upcoming();
        } elseif ($status === 'past') {
            $query->past();
        } else {
            $query->orderBy('ride_date');
        }

        if ($request->filled('from')) {
            $query->whereDate('ride_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('ride_date', '<=', $request->date('to'));
        }

        $rides = $query->paginate(15)->withQueryString();

        return view('rides.index', [
            'rides' => $rides,
            'status' => $status,
            'from' => $request->get('from'),
            'to' => $request->get('to'),
        ]);
    }

    public function create()
    {
        $routes = Route::public()
            ->orderBy('name')
            ->get(['id', 'name', 'distance_km', 'elevation_gain_m', 'difficulty', 'gpx_data']);

        $routes->each(function ($route) {
            $coordinates = $route->geometry['coordinates'] ?? [];
            $start = $coordinates[0] ?? null;
            $route->start_lat = $start[1] ?? null;
            $route->start_lng = $start[0] ?? null;
        });

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

        $newVersion = $criticalChanged ? $ride->version + 1 : $ride->version;

        // Capture current attendees before the version-change trigger removes them.
        $removedAttendees = $criticalChanged
            ? $ride->attendees()
                ->where('ride_version', '<', $newVersion)
                ->where('user_id', '!=', $ride->organizer_id)
                ->with('user')
                ->get()
            : collect();

        DB::transaction(function () use ($request, $ride, $criticalChanged, $newVersion, $removedAttendees) {
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
                'version' => $newVersion,
            ]);

            if ($criticalChanged) {
                // Keep the organizer confirmed on the new version.
                $ride->attendees()->updateOrCreate(
                    ['user_id' => $ride->organizer_id, 'ride_version' => $newVersion],
                    ['status' => 'confirmed']
                );

                foreach ($removedAttendees as $attendee) {
                    $attendee->user?->notify(new RideChanged(
                        $ride,
                        'The route or date for this ride changed. Please confirm your attendance again.'
                    ));
                }
            }
        });

        return redirect()->route('rides.show', $ride)
            ->with('success', 'Ride updated successfully!');
    }

    public function destroy(GroupRide $ride)
    {
        $this->authorize('delete', $ride);

        $ride->loadMissing('route');

        $attendees = $ride->confirmedAttendees()
            ->where('ride_version', $ride->version)
            ->where('user_id', '!=', $ride->organizer_id)
            ->with('user')
            ->get();

        $message = 'The ride "'.($ride->title ?: $ride->route?->name).'" on '
            .$ride->ride_date->format('l, M j, Y').' has been cancelled.';

        foreach ($attendees as $attendee) {
            $attendee->user?->notify(new RideCancelled($ride, $message));
        }

        $ride->delete();

        return redirect()->route('rides.index')
            ->with('success', 'Ride cancelled!');
    }
}
