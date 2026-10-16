<?php

namespace App\Console\Commands;

use App\Events\RideReminder;
use App\Models\GroupRide;
use Illuminate\Console\Command;

class SendRideReminders extends Command
{
    protected $signature = 'rides:send-reminders {--hours= : Hours before a ride to remind (defaults to config rides.reminder_hours)} {--dry-run : Report how many rides would be reminded without notifying}';

    protected $description = 'Notify confirmed attendees about rides starting within the reminder window';

    public function handle(): int
    {
        $hours = (int) ($this->option('hours') ?: config('rides.reminder_hours', 24));
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;

        GroupRide::query()
            ->whereIn('status', ['planned', 'confirmed'])
            ->whereNull('reminder_sent_at')
            ->where('ride_date', '>', now())
            ->where('ride_date', '<=', now()->addHours($hours))
            ->with(['route', 'confirmedAttendees.user'])
            ->chunkById(100, function ($rides) use (&$sent, $dryRun) {
                foreach ($rides as $ride) {
                    $users = $ride->confirmedAttendees
                        ->filter(fn ($attendee) => $attendee->user && $attendee->ride_version === $ride->version)
                        ->map(fn ($attendee) => $attendee->user)
                        ->values();

                    if ($users->isEmpty()) {
                        continue;
                    }

                    if ($dryRun) {
                        $sent += $users->count();

                        continue;
                    }

                    $ride->forceFill(['reminder_sent_at' => now()])->save();

                    event(new RideReminder($ride, $users));
                    $sent += $users->count();
                }
            });

        $verb = $dryRun ? 'Would send' : 'Sent';
        $this->info("{$verb} {$sent} ride reminder(s).");

        return self::SUCCESS;
    }
}
