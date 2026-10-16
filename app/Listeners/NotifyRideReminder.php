<?php

namespace App\Listeners;

use App\Events\RideReminder;
use App\Listeners\Concerns\BuildsDiscordMessages;
use App\Notifications\RideReminder as RideReminderNotification;

class NotifyRideReminder
{
    use BuildsDiscordMessages;

    public function handle(RideReminder $event): void
    {
        foreach ($event->users as $user) {
            $user->notify(new RideReminderNotification($event->ride));
        }

        $this->sendToDiscord(
            "Reminder: ride {$this->link($this->rideName($event->ride), route('rides.show', $event->ride))} starts at {$this->rideWhen($event->ride)}."
        );
    }
}
