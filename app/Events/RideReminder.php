<?php

namespace App\Events;

use App\Models\GroupRide;
use App\Models\User;
use Illuminate\Support\Collection;

class RideReminder
{
    /**
     * @param  Collection<int, User>  $users
     */
    public function __construct(
        public GroupRide $ride,
        public Collection $users,
    ) {}
}
