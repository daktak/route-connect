<?php

namespace App\Listeners;

use App\Events\RideChanged;
use App\Listeners\Concerns\BuildsDiscordMessages;
use App\Notifications\RideChanged as RideChangedNotification;

class NotifyRideChanged
{
    use BuildsDiscordMessages;

    public function handle(RideChanged $event): void
    {
        $ride = $event->ride;
        $ride->loadMissing('route');

        foreach ($event->attendees as $attendee) {
            $attendee->user?->notify(new RideChangedNotification($ride, $event->message));
        }

        $this->sendToDiscord(
            "{$event->actor->name} updated ride {$this->link($this->rideName($ride), route('rides.show', $ride))} — the route or date changed; attendees must reconfirm."
        );
    }
}
