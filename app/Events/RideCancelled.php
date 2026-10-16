<?php

namespace App\Events;

use App\Models\GroupRide;
use App\Models\RideAttendee;
use Illuminate\Support\Collection;

class RideCancelled
{
    /**
     * @param  Collection<int, RideAttendee>  $attendees
     */
    public function __construct(
        public GroupRide $ride,
        public string $message,
        public Collection $attendees,
    ) {}
}
