<?php

namespace App\Events;

use App\Models\GroupRide;
use App\Models\User;

class RideJoined
{
    public function __construct(public GroupRide $ride, public User $attendee) {}
}
