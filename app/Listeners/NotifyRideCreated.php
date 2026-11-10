<?php

namespace App\Listeners;

use App\Events\RideCreated;
use App\Listeners\Concerns\BuildsDiscordMessages;

class NotifyRideCreated
{
    use BuildsDiscordMessages;

    public function handle(RideCreated $event): void
    {
        $ride = $event->ride;
        $ride->loadMissing('route');

        $routeName = $ride->route?->name ?: $this->rideName($ride);
        $routeUrl = $ride->route
            ? route('routes.show', $ride->route)
            : route('rides.show', $ride);
        $meetingName = $ride->meeting_point_name;
        $mapUrl = $ride->meetingPointMapUrl();
        $meeting = $meetingName && $mapUrl
            ? $this->link($meetingName, $mapUrl)
            : ($meetingName ?: 'TBA');

        $this->sendToDiscord(
            "{$event->creator->name} has created a ride "
            .$this->link($this->rideName($ride), route('rides.show', $ride))
            ." using route {$this->link($routeName, $routeUrl)}"
            ." leaving from {$meeting} at {$this->rideWhen($ride)}."
        );
    }
}
