<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array(strtolower($this->email), config('admin.emails', []), true);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class)->latest();
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(RouteRating::class)->latest();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RouteComment::class)->latest();
    }

    public function organizedRides(): HasMany
    {
        return $this->hasMany(GroupRide::class, 'organizer_id')->latest('ride_date');
    }

    public function attendedRides(): HasMany
    {
        return $this->hasMany(RideAttendee::class)->with('groupRide.route')->latest('joined_at');
    }

    public function upcomingRides()
    {
        return $this->attendedRides()
            ->whereHas('groupRide', fn ($q) => $q->where('ride_date', '>', now())
                ->whereIn('status', ['planned', 'confirmed']));
    }

    public function getRouteStats()
    {
        return DB::select('SELECT * FROM get_user_route_stats(?)', [$this->id])[0] ?? null;
    }
}
