<?php

namespace App\Events;

use App\Models\GroupRide;
use App\Models\User;

class RideCreated
{
    public function __construct(public GroupRide $ride, public User $creator) {}
}
