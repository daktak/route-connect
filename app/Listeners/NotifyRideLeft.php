<?php

namespace App\Listeners;

use App\Events\RideLeft;
use App\Listeners\Concerns\BuildsDiscordMessages;
use App\Notifications\RideLeft as RideLeftNotification;

class NotifyRideLeft
{
    use BuildsDiscordMessages;

    public function handle(RideLeft $event): void
    {
        $ride = $event->ride;
        $ride->loadMissing('route', 'organizer');

        if ($ride->organizer_id !== $event->attendee->id && $ride->organizer) {
            $ride->organizer->notify(new RideLeftNotification($ride, $event->attendee));
        }

        $this->sendToDiscord(
            "{$event->attendee->name} has left ride {$this->link($this->rideName($ride), route('rides.show', $ride))}."
        );
    }
}
