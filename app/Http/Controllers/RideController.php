<?php

namespace App\Http\Controllers;

use App\Events\RideCancelled;
use App\Events\RideChanged;
use App\Events\RideCreated;
use App\Models\GroupRide;
use App\Models\GroupRideRouteOption;
use App\Models\RideAttendee;
use App\Models\Route;
use App\Services\AirQualityService;
use App\Services\RecurrenceService;
use App\Services\TimezoneService;
use App\Services\WeatherService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $query = GroupRide::with([
            'route' => fn ($q) => $q
                ->select('id', 'name', 'distance_km', 'elevation_gain_m', 'difficulty', 'estimated_time_min')
                ->selectRaw('ST_Y(ST_StartPoint(geometry)) as start_lat, ST_X(ST_StartPoint(geometry)) as start_lng'),
            'organizer',
            'attendees.user',
        ])->withCount(['attendees', 'routeOptions']);

        if ($status === 'upcoming') {
            $query->upcoming();
        } elseif ($status === 'past') {
            $query->past();
        } else {
            $query->orderBy('ride_date');
        }

        if ($request->filled('from')) {
            $from = Carbon::parse($request->from, config('app.default_timezone'))->startOfDay()->utc();
            $query->where('ride_date', '>=', $from);
        }
        if ($request->filled('to')) {
            $to = Carbon::parse($request->to, config('app.default_timezone'))->endOfDay()->utc();
            $query->where('ride_date', '<=', $to);
        }

        $rides = $query->paginate(14)->withQueryString();

        $rides->getCollection()->each(function ($ride) {
            $ride->weather = null;
            $ride->airQuality = null;
        });

        $upcoming = $rides->getCollection()
            ->filter(fn ($ride) => $ride->ride_date->isFuture())
            ->values();

        if ($upcoming->isNotEmpty()) {
            $weather = app(WeatherService::class)->forRides($upcoming);
            $upcoming->each(fn ($ride) => $ride->weather = $weather[$ride->id] ?? null);

            $airQuality = app(AirQualityService::class)->forRides($upcoming);
            $upcoming->each(fn ($ride) => $ride->airQuality = $airQuality[$ride->id] ?? null);
        }

        return view('rides.index', [
            'rides' => $rides,
            'status' => $status,
            'from' => $request->get('from'),
            'to' => $request->get('to'),
        ]);
    }

    public function create(Request $request)
    {
        $preselectedRouteId = $request->query('route');

        $routes = Route::public()
            ->orderBy('name')
            ->get(['id', 'name', 'distance_km', 'elevation_gain_m', 'difficulty', 'gpx_data']);

        $routes->each(function ($route) {
            $coordinates = $route->geometry['coordinates'] ?? [];
            $start = $coordinates[0] ?? null;
            $route->start_lat = $start[1] ?? null;
            $route->start_lng = $start[0] ?? null;
        });

        $selectedRouteIds = array_map('intval', (array) old(
            'route_ids',
            $preselectedRouteId ? [$preselectedRouteId] : []
        ));

        return view('rides.create', compact('routes', 'selectedRouteIds'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'route_ids' => 'required|array|min:1',
            'route_ids.*' => 'exists:routes,id',
            'title' => 'nullable|string|max:255',
            'ride_date' => 'required|date|after:now',
            'meeting_point_name' => 'nullable|string|max:255',
            'meeting_point_lat' => 'nullable|numeric|between:-90,90',
            'meeting_point_lng' => 'nullable|numeric|between:-180,180',
            'max_participants' => 'nullable|integer|min:2',
            'description' => 'nullable|string',
            'recurrence' => 'nullable|array',
            'recurrence.freq' => 'nullable|in:daily,weekly,fortnightly,monthly',
            'recurrence.until' => 'nullable|date|after:ride_date|before:+3 months',
            'recurrence.count' => 'nullable|integer|min:2|max:52',
            'recurrence.by_weekday' => 'nullable|integer|between:0,6',
            'recurrence.by_week' => 'nullable|integer|between:1,5',
        ]);

        $routeIds = array_values(array_unique(array_map('intval', $request->route_ids)));

        $rideDateTime = $this->rideDateTime($request, $routeIds);

        $recurrenceRule = null;
        if ($request->filled('recurrence.freq')) {
            $recurrenceRule = array_filter([
                'freq' => $request->input('recurrence.freq'),
                'interval' => 1,
                'until' => $request->input('recurrence.until'),
                'count' => $request->input('recurrence.count'),
                'by_weekday' => $request->input('recurrence.by_weekday', $rideDateTime['ride_date']->dayOfWeek),
                'by_week' => $request->input('recurrence.by_week'),
            ], fn ($v) => $v !== null && $v !== '');
        }

        $root = GroupRide::create([
            'route_id' => $routeIds[0],
            'organizer_id' => Auth::id(),
            'title' => $request->title,
            'ride_date' => $rideDateTime['ride_date'],
            'timezone' => $rideDateTime['timezone'],
            'meeting_point_name' => $request->meeting_point_name,
            'meeting_point_lat' => $request->meeting_point_lat,
            'meeting_point_lng' => $request->meeting_point_lng,
            'max_participants' => $request->max_participants,
            'description' => $request->description,
            'status' => 'planned',
            'version' => 1,
            'recurrence_rule' => $recurrenceRule,
            'recurrence_index' => 0,
        ]);

        foreach ($routeIds as $index => $routeId) {
            GroupRideRouteOption::create([
                'group_ride_id' => $root->id,
                'route_id' => $routeId,
                'sort_order' => $index,
            ]);
        }

        RideAttendee::create([
            'group_ride_id' => $root->id,
            'user_id' => Auth::id(),
            'ride_version' => 1,
            'status' => 'confirmed',
        ]);

        event(new RideCreated($root, Auth::user()));

        // Generate occurrences
        if ($recurrenceRule) {
            $until = $recurrenceRule['until'] ?? null ? Carbon::parse($recurrenceRule['until']) : null;
            $dates = app(RecurrenceService::class)->occurrences($root->ride_date, $recurrenceRule, $until, $recurrenceRule['count'] ?? null);
            // skip first (root)
            $dates->shift();
            foreach ($dates as $i => $date) {
                $occ = GroupRide::create([
                    'route_id' => $routeIds[0],
                    'organizer_id' => Auth::id(),
                    'title' => $request->title,
                    'ride_date' => $date,
                    'timezone' => $rideDateTime['timezone'],
                    'meeting_point_name' => $request->meeting_point_name,
                    'meeting_point_lat' => $request->meeting_point_lat,
                    'meeting_point_lng' => $request->meeting_point_lng,
                    'max_participants' => $request->max_participants,
                    'description' => $request->description,
                    'status' => 'planned',
                    'version' => 1,
                    'recurrence_series_id' => $root->id,
                    'recurrence_index' => $i + 1,
                ]);
                foreach ($routeIds as $index => $routeId) {
                    GroupRideRouteOption::create([
                        'group_ride_id' => $occ->id,
                        'route_id' => $routeId,
                        'sort_order' => $index,
                    ]);
                }
                RideAttendee::create([
                    'group_ride_id' => $occ->id,
                    'user_id' => Auth::id(),
                    'ride_version' => 1,
                    'status' => 'confirmed',
                ]);
                event(new RideCreated($occ, Auth::user()));
            }
        }

        return redirect()->route('rides.show', $root)
            ->with('success', 'Group ride created! You are automatically attending.');
    }

    public function show(GroupRide $ride)
    {
        $ride->load([
            'route.features',
            'organizer',
            'attendees.user',
            'attendees' => fn ($q) => $q->where('ride_version', $ride->version),
            'routeOptions.route',
        ]);

        $ride->weather = $ride->ride_date->isFuture()
            ? app(WeatherService::class)->forRide($ride)
            : null;

        $ride->airQuality = $ride->ride_date->isFuture()
            ? app(AirQualityService::class)->forRide($ride)
            : null;

        return view('rides.show', [
            'ride' => $ride,
            'multipleRoutes' => $ride->routeOptions->count() > 1,
        ]);
    }

    public function edit(GroupRide $ride)
    {
        $this->authorize('update', $ride);

        $routes = Route::public()->orderBy('name')->get(['id', 'name', 'distance_km', 'elevation_gain_m', 'difficulty']);

        $selectedRouteIds = $ride->routeOptions()->pluck('route_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('rides.edit', compact('ride', 'routes', 'selectedRouteIds'));
    }

    public function update(Request $request, GroupRide $ride)
    {
        $this->authorize('update', $ride);

        $request->validate([
            'route_ids' => 'required|array|min:1',
            'route_ids.*' => 'exists:routes,id',
            'title' => 'nullable|string|max:255',
            'ride_date' => 'required|date',
            'meeting_point_name' => 'nullable|string|max:255',
            'meeting_point_lat' => 'nullable|numeric|between:-90,90',
            'meeting_point_lng' => 'nullable|numeric|between:-180,180',
            'max_participants' => 'nullable|integer|min:2',
            'description' => 'nullable|string',
            'status' => ['required', Rule::in(['planned', 'confirmed', 'cancelled', 'completed'])],
        ]);

        $routeIds = array_values(array_unique(array_map('intval', $request->route_ids)));

        $rideDateTime = $this->rideDateTime($request, $routeIds);
        $rideDate = $rideDateTime['ride_date'];

        $originalRideDate = $ride->ride_date->copy();
        $deltaMinutes = $originalRideDate->diffInMinutes($rideDate, false);

        $currentRouteIds = $ride->routeOptions()->pluck('route_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $criticalChanged = $routeIds !== $currentRouteIds
            || $ride->ride_date->ne($rideDate);

        $newVersion = $criticalChanged ? $ride->version + 1 : $ride->version;

        // Capture current attendees before the version-change trigger removes them.
        $removedAttendees = $criticalChanged
            ? $ride->attendees()
                ->where('ride_version', '<', $newVersion)
                ->where('user_id', '!=', $ride->organizer_id)
                ->with('user')
                ->get()
            : collect();

        $rideTimezone = $rideDateTime['timezone'];

        DB::transaction(function () use ($request, $ride, $criticalChanged, $newVersion, $routeIds, $rideDate, $rideTimezone) {
            $ride->update([
                'route_id' => $routeIds[0],
                'title' => $request->title,
                'ride_date' => $rideDate,
                'timezone' => $rideTimezone,
                'meeting_point_name' => $request->meeting_point_name,
                'meeting_point_lat' => $request->meeting_point_lat,
                'meeting_point_lng' => $request->meeting_point_lng,
                'max_participants' => $request->max_participants,
                'description' => $request->description,
                'status' => $request->status,
                'version' => $newVersion,
            ]);

            if ($criticalChanged) {
                // Rebuild the route options for the new version.
                $ride->routeOptions()->delete();

                foreach ($routeIds as $index => $routeId) {
                    GroupRideRouteOption::create([
                        'group_ride_id' => $ride->id,
                        'route_id' => $routeId,
                        'sort_order' => $index,
                    ]);
                }

                // Keep the organizer confirmed on the new version.
                $ride->attendees()->updateOrCreate(
                    ['user_id' => $ride->organizer_id, 'ride_version' => $newVersion],
                    ['status' => 'confirmed']
                );
            }
        });

        if ($criticalChanged) {
            event(new RideChanged(
                $ride,
                'The route or date for this ride changed. Please confirm your attendance again.',
                $removedAttendees,
                Auth::user(),
            ));
        }

        if ($request->input('edit_scope') === 'series') {
            $root = $ride->seriesRoot ?: $ride;

            if ($request->input('status') === 'cancelled') {
                GroupRide::where(function ($q) use ($root) {
                    $q->where('id', $root->id)->orWhere('recurrence_series_id', $root->id);
                })->where('ride_date', '>', $originalRideDate)->update(['status' => 'cancelled']);
            } else {
                $targets = GroupRide::where(function ($q) use ($root) {
                    $q->where('id', $root->id)->orWhere('recurrence_series_id', $root->id);
                })->where('ride_date', '>=', $originalRideDate)->get();

                foreach ($targets as $target) {
                    if ($target->id === $ride->id) {
                        continue;
                    }
                    $newDate = $target->ride_date->copy()->addMinutes($deltaMinutes);
                    $target->update([
                        'title' => $request->title,
                        'ride_date' => $newDate,
                        'timezone' => $rideDateTime['timezone'],
                        'meeting_point_name' => $request->meeting_point_name,
                        'meeting_point_lat' => $request->meeting_point_lat,
                        'meeting_point_lng' => $request->meeting_point_lng,
                        'max_participants' => $request->max_participants,
                        'description' => $request->description,
                        'status' => $request->status,
                    ]);
                }
            }
        }

        if ($request->input('status') === 'cancelled') {
            return redirect()->route('rides.index')
                ->with('success', 'Ride cancelled!');
        }

        return redirect()->route('rides.show', $ride)
            ->with('success', 'Ride updated successfully!');
    }

    public function destroy(GroupRide $ride)
    {
        $this->authorize('delete', $ride);

        $ride->loadMissing('route');

        if (request()->input('scope') === 'series') {
            $root = $ride->seriesRoot ?: $ride;
            GroupRide::where(function ($q) use ($root) {
                $q->where('id', $root->id)->orWhere('recurrence_series_id', $root->id);
            })->where('ride_date', '>=', $ride->ride_date)->delete();

            return redirect()->route('rides.index')->with('success', 'Ride series deleted!');
        }

        $attendees = $ride->confirmedAttendees()
            ->where('ride_version', $ride->version)
            ->where('user_id', '!=', $ride->organizer_id)
            ->with('user')
            ->get();

        $message = 'The ride "'.($ride->title ?: $ride->route?->name).'" on '
            .$ride->ride_date->timezone($ride->timezoneName())->format('l, M j, Y').' has been cancelled.';

        event(new RideCancelled($ride, $message, $attendees));

        $ride->delete();

        return redirect()->route('rides.index')
            ->with('success', 'Ride cancelled!');
    }

    /**
     * Interpret the submitted datetime-local value in the ride's location
     * timezone (meeting point, else the primary route's start point) and
     * return the UTC instant plus the resolved timezone.
     *
     * @return array{timezone: string, ride_date: Carbon}
     */
    private function rideDateTime(Request $request, array $routeIds): array
    {
        $lat = $request->filled('meeting_point_lat') ? (float) $request->meeting_point_lat : null;
        $lng = $request->filled('meeting_point_lng') ? (float) $request->meeting_point_lng : null;

        if (! $lat || ! $lng) {
            $route = Route::find($routeIds[0] ?? null);
            $coordinates = $route?->geometry['coordinates'] ?? [];
            $start = $coordinates[0] ?? null;
            $lat = $start[1] ?? null;
            $lng = $start[0] ?? null;
        }

        $tz = ($lat && $lng)
            ? app(TimezoneService::class)->forCoordinates($lat, $lng)
            : null;
        $tz = $tz ?: config('app.default_timezone');

        return [
            'timezone' => $tz,
            'ride_date' => Carbon::parse($request->ride_date, $tz)->utc(),
        ];
    }
}
