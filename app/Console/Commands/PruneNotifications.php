<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--days= : Override retention days} {--dry-run : Report how many notifications would be pruned without deleting}';

    protected $description = 'Delete read notifications older than the retention period';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('notifications.retention_days', 90)));

        $query = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays($days));

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would prune {$count} read notification(s) older than {$days} days.");

            return self::SUCCESS;
        }

        if ($count > 0) {
            $query->delete();
        }

        $this->info("Pruned {$count} read notification(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
