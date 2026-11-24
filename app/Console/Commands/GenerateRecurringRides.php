<?php

namespace App\Console\Commands;

use App\Models\GroupRide;
use App\Models\RideAttendee;
use App\Services\RecurrenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateRecurringRides extends Command
{
    protected $signature = 'rides:generate-recurring';

    protected $description = 'Extend recurring ride series with future occurrences';

    public function handle(RecurrenceService $service): int
    {
        $roots = GroupRide::whereNotNull('recurrence_rule')->get();
        foreach ($roots as $root) {
            $rule = $root->recurrence_rule;
            $latest = GroupRide::where('recurrence_series_id', $root->id)->orderByDesc('ride_date')->first()
                ?: $root;
            $until = isset($rule['until']) ? Carbon::parse($rule['until']) : null;
            $count = $rule['count'] ?? null;
            $existingCount = GroupRide::where('recurrence_series_id', $root->id)->count() + 1;
            if ($count && $existingCount >= $count) {
                continue;
            }
            // generate next occurrence after latest
            $next = $service->occurrences($root->ride_date, $rule, $until, $count)
                ->first(fn ($d) => $d->greaterThan($latest->ride_date));
            if (! $next) {
                continue;
            }
            if ($until && $next->greaterThan($until)) {
                continue;
            }
            $occ = GroupRide::create([
                'route_id' => $root->route_id,
                'organizer_id' => $root->organizer_id,
                'title' => $root->title,
                'ride_date' => $next,
                'timezone' => $root->timezone,
                'meeting_point_name' => $root->meeting_point_name,
                'meeting_point_lat' => $root->meeting_point_lat,
                'meeting_point_lng' => $root->meeting_point_lng,
                'max_participants' => $root->max_participants,
                'description' => $root->description,
                'status' => 'planned',
                'version' => 1,
                'recurrence_series_id' => $root->id,
                'recurrence_index' => $existingCount,
            ]);
            foreach ($root->routeOptions as $opt) {
                $occ->routeOptions()->create(['route_id' => $opt->route_id, 'sort_order' => $opt->sort_order]);
            }
            if ($root->organizer_id) {
                RideAttendee::create([
                    'group_ride_id' => $occ->id,
                    'user_id' => $root->organizer_id,
                    'ride_version' => 1,
                    'status' => 'confirmed',
                ]);
            }
        }

        return self::SUCCESS;
    }
}
