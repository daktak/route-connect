<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->isAdmin();
    }

    public function view(User $auth, User $user): bool
    {
        return $auth->isAdmin();
    }

    public function update(User $auth, User $user): bool
    {
        return $auth->isAdmin();
    }

    public function delete(User $auth, User $user): bool
    {
        return $auth->isAdmin()
            && $auth->id !== $user->id
            && ! $user->isAdmin();
    }
}
