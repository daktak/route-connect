<?php

namespace App\Policies;

use App\Models\RouteComment;
use App\Models\User;

class CommentPolicy
{
    public function update(User $user, RouteComment $comment): bool
    {
        return $comment->user_id === $user->id || $user->isAdmin();
    }

    public function delete(User $user, RouteComment $comment): bool
    {
        return $comment->user_id === $user->id || $user->isAdmin();
    }
}
