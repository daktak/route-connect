<?php

namespace App\Events;

use App\Models\Route;
use App\Models\RouteComment;
use App\Models\User;

class CommentCreated
{
    public function __construct(
        public Route $route,
        public RouteComment $comment,
        public User $author,
    ) {}
}
