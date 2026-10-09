<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideAttendee extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_ride_id',
        'user_id',
        'ride_version',
        'status',
        'note',
    ];

    protected $casts = [
        'ride_version' => 'integer',
        'joined_at' => 'datetime',
    ];

    public function groupRide(): BelongsTo
    {
        return $this->belongsTo(GroupRide::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCurrentVersion(): bool
    {
        return $this->ride_version === $this->groupRide?->version;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'confirmed' => "I'm In",
            'tentative' => 'Maybe',
            'declined' => "Can't Make It",
            default => $this->status,
        };
    }
}
