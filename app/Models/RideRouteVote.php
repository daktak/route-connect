<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideRouteVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_ride_id',
        'route_id',
        'user_id',
        'ride_version',
    ];

    protected $casts = [
        'ride_version' => 'integer',
    ];

    public function groupRide(): BelongsTo
    {
        return $this->belongsTo(GroupRide::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
