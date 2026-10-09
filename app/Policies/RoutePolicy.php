<?php

namespace App\Policies;

use App\Models\Route;
use App\Models\User;

class RoutePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Route $route): bool
    {
        return $route->is_public || $route->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Route $route): bool
    {
        return $route->user_id === $user->id || $user->isAdmin();
    }

    public function delete(User $user, Route $route): bool
    {
        return $route->user_id === $user->id || $user->isAdmin();
    }

    public function download(User $user, Route $route): bool
    {
        return $route->is_public || $route->user_id === $user->id;
    }
}
