<?php

namespace App\Listeners;

use App\Events\RideCancelled;
use App\Listeners\Concerns\BuildsDiscordMessages;
use App\Notifications\RideCancelled as RideCancelledNotification;

class NotifyRideCancelled
{
    use BuildsDiscordMessages;

    public function handle(RideCancelled $event): void
    {
        $ride = $event->ride;
        $ride->loadMissing('route');

        foreach ($event->attendees as $attendee) {
            $attendee->user?->notify(new RideCancelledNotification($ride, $event->message));
        }

        $this->sendToDiscord(
            "Ride {$this->rideName($ride)} scheduled for {$this->rideWhen($ride)} has been cancelled."
        );
    }
}
