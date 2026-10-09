<?php

namespace App\Policies;

use App\Models\GroupRide;
use App\Models\User;

class RidePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GroupRide $ride): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, GroupRide $ride): bool
    {
        return $ride->organizer_id === $user->id || $user->isAdmin();
    }

    public function delete(User $user, GroupRide $ride): bool
    {
        return $ride->organizer_id === $user->id || $user->isAdmin();
    }

    public function join(User $user, GroupRide $ride): bool
    {
        return $ride->status !== 'cancelled' && ! $ride->is_full;
    }

    public function leave(User $user, GroupRide $ride): bool
    {
        return true;
    }
}
