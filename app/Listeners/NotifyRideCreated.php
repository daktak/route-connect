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
        $meeting = $ride->meeting_point_name ?: 'TBA';

        $this->sendToDiscord(
            "{$event->creator->name} has created a ride "
            .$this->link($this->rideName($ride), route('rides.show', $ride))
            ." using route {$this->link($routeName, $routeUrl)}"
            ." leaving from {$meeting} at {$this->rideWhen($ride)}."
        );
    }
}
