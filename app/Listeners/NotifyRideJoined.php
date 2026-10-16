<?php

namespace App\Listeners;

use App\Events\RideJoined;
use App\Listeners\Concerns\BuildsDiscordMessages;
use App\Notifications\RideJoined as RideJoinedNotification;

class NotifyRideJoined
{
    use BuildsDiscordMessages;

    public function handle(RideJoined $event): void
    {
        $ride = $event->ride;
        $ride->loadMissing('route', 'organizer');

        if ($ride->organizer_id !== $event->attendee->id && $ride->organizer) {
            $ride->organizer->notify(new RideJoinedNotification($ride, $event->attendee));
        }

        $this->sendToDiscord(
            "{$event->attendee->name} has joined ride {$this->link($this->rideName($ride), route('rides.show', $ride))}."
        );
    }
}
