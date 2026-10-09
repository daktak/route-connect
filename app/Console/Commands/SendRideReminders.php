<?php

namespace App\Console\Commands;

use App\Models\GroupRide;
use App\Notifications\RideReminder;
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
                    foreach ($ride->confirmedAttendees as $attendee) {
                        if (! $attendee->user || $attendee->ride_version !== $ride->version) {
                            continue;
                        }

                        $attendee->user->notify(new RideReminder($ride));
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} ride reminder(s).");

        return self::SUCCESS;
    }
}
