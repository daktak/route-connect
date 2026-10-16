<?php

namespace App\Events;

use App\Models\GroupRide;
use App\Models\User;

class RideLeft
{
    public function __construct(public GroupRide $ride, public User $attendee) {}
}
