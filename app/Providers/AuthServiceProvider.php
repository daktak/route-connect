<?php

namespace App\Providers;

use App\Models\GroupRide;
use App\Models\Route;
use App\Models\RouteComment;
use App\Models\User;
use App\Policies\CommentPolicy;
use App\Policies\RidePolicy;
use App\Policies\RoutePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Route::class => RoutePolicy::class,
        GroupRide::class => RidePolicy::class,
        RouteComment::class => CommentPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
