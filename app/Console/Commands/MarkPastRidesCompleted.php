<?php

namespace App\Console\Commands;

use App\Models\GroupRide;
use Illuminate\Console\Command;

class MarkPastRidesCompleted extends Command
{
    protected $signature = 'rides:mark-past-completed {--dry-run : Report how many rides would be completed without updating}';

    protected $description = 'Mark past planned/confirmed group rides as completed';

    public function handle(): int
    {
        $query = GroupRide::query()
            ->whereIn('status', ['planned', 'confirmed'])
            ->where('ride_date', '<=', now());

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would mark {$count} ride(s) as completed.");

            return self::SUCCESS;
        }

        if ($count > 0) {
            $query->update(['status' => 'completed']);
        }

        $this->info("Marked {$count} ride(s) as completed.");

        return self::SUCCESS;
    }
}
