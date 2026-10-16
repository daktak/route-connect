<?php

namespace App\Console\Commands;

use App\Events\RideReminder;
use App\Models\GroupRide;
use Illuminate\Console\Command;

class SendRideReminders extends Command
{
    protected $signature = 'rides:send-reminders {--hours=24}';

    protected $description = 'Notify confirmed attendees about rides starting soon';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $sent = 0;

        GroupRide::query()
            ->whereIn('status', ['planned', 'confirmed'])
            ->whereBetween('ride_date', [now(), now()->addHours($hours)])
            ->with(['route', 'confirmedAttendees.user'])
            ->chunkById(100, function ($rides) use (&$sent) {
                foreach ($rides as $ride) {
                    $users = $ride->confirmedAttendees
                        ->filter(fn ($attendee) => $attendee->user && $attendee->ride_version === $ride->version)
                        ->map(fn ($attendee) => $attendee->user)
                        ->values();

                    if ($users->isEmpty()) {
                        continue;
                    }

                    event(new RideReminder($ride, $users));
                    $sent += $users->count();
                }
            });

        $this->info("Sent {$sent} ride reminder(s).");

        return self::SUCCESS;
    }
}
