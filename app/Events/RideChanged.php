<?php

namespace App\Events;

use App\Models\GroupRide;
use App\Models\RideAttendee;
use App\Models\User;
use Illuminate\Support\Collection;

class RideChanged
{
    /**
     * @param  Collection<int, RideAttendee>  $attendees
     */
    public function __construct(
        public GroupRide $ride,
        public string $message,
        public Collection $attendees,
        public User $actor,
    ) {}
}
