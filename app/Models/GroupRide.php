<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupRide extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'organizer_id',
        'title',
        'description',
        'ride_date',
        'meeting_point_lat',
        'meeting_point_lng',
        'meeting_point_name',
        'max_participants',
        'status',
        'version',
    ];

    protected $casts = [
        'ride_date' => 'datetime',
        'meeting_point_lat' => 'decimal:8',
        'meeting_point_lng' => 'decimal:8',
        'max_participants' => 'integer',
        'version' => 'integer',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(RideAttendee::class)->with('user');
    }

    public function confirmedAttendees(): HasMany
    {
        return $this->hasMany(RideAttendee::class)
            ->where('status', 'confirmed')
            ->with('user');
    }

    public function getAttendeesCountAttribute(): int
    {
        return $this->confirmedAttendees()
            ->where('ride_version', $this->version)
            ->count();
    }

    public function getIsFullAttribute(): bool
    {
        return $this->max_participants && $this->attendees_count >= $this->max_participants;
    }

    public function getIsPastAttribute(): bool
    {
        return $this->ride_date->isPast();
    }

    public function scopeUpcoming($query)
    {
        return $query->where('ride_date', '>', now())
            ->whereIn('status', ['planned', 'confirmed'])
            ->orderBy('ride_date');
    }

    public function scopePast($query)
    {
        return $query->where('ride_date', '<=', now())
            ->orderByDesc('ride_date');
    }
}
