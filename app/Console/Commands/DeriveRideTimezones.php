<?php

namespace App\Console\Commands;

use App\Models\GroupRide;
use App\Services\TimezoneService;
use Illuminate\Console\Command;

class DeriveRideTimezones extends Command
{
    protected $signature = 'rides:derive-timezones {--dry-run : Report how many rides would be updated without updating}';

    protected $description = 'Derive and store location timezones for group rides missing one';

    public function handle(TimezoneService $timezoneService): int
    {
        $rides = GroupRide::query()
            ->whereNull('timezone')
            ->with('route')
            ->get();

        $updated = 0;
        $missing = 0;

        foreach ($rides as $ride) {
            $tz = $timezoneService->forRide($ride);

            if (! $tz) {
                $missing++;

                continue;
            }

            if (! $this->option('dry-run')) {
                $ride->update(['timezone' => $tz]);
            }

            $updated++;
        }

        $suffix = $this->option('dry-run') ? ' would be updated' : ' updated';

        $this->info("{$updated} ride(s){$suffix}; {$missing} could not be resolved.");

        return self::SUCCESS;
    }
}
