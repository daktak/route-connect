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
        'timezone',
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
        'reminder_sent_at' => 'datetime',
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

    public function routeOptions(): HasMany
    {
        return $this->hasMany(GroupRideRouteOption::class)->orderBy('sort_order');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(RideRouteVote::class);
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

    /**
     * The canonical timezone for this ride (location-derived), falling back to
     * the site default when unknown.
     */
    public function timezoneName(): string
    {
        return $this->timezone ?: config('app.default_timezone');
    }

    public function getIsPastAttribute(): bool
    {
        return $this->ride_date->isPast();
    }

    public function getRouteOptionsCountAttribute(): int
    {
        if (array_key_exists('route_options_count', $this->attributes)) {
            return (int) $this->attributes['route_options_count'];
        }

        return $this->routeOptions()->count();
    }

    public function hasVoting(): bool
    {
        $count = array_key_exists('route_options_count', $this->attributes)
            ? (int) $this->attributes['route_options_count']
            : $this->routeOptions->count();

        return $count > 1
            && ! in_array($this->status, ['cancelled', 'completed'], true);
    }

    public function voteCounts(): array
    {
        return $this->votes()
            ->where('ride_version', $this->version)
            ->selectRaw('route_id, COUNT(*) as total')
            ->groupBy('route_id')
            ->pluck('total', 'route_id')
            ->toArray();
    }

    public function leadingRouteId(): ?int
    {
        $counts = $this->voteCounts();

        return $this->routeOptions
            ->sortByDesc(fn ($option) => $counts[$option->route_id] ?? 0)
            ->first()?->route_id;
    }

    public function leadingRoute(): ?Route
    {
        $leadingRouteId = $this->leadingRouteId();

        if ($leadingRouteId) {
            $option = $this->routeOptions->firstWhere('route_id', $leadingRouteId);
            if ($option?->route) {
                return $option->route;
            }
        }

        return $this->route;
    }

    public function currentUserVote(?int $userId): ?RideRouteVote
    {
        if (! $userId) {
            return null;
        }

        return $this->votes()
            ->where('user_id', $userId)
            ->where('ride_version', $this->version)
            ->first();
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
