<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'gpx_data',
        'geometry',
        'distance_km',
        'elevation_gain_m',
        'estimated_time_min',
        'difficulty',
        'is_public',
        'gpx_file_path',
    ];

    protected $casts = [
        'gpx_data' => 'array',
        'geometry' => 'json',
        'distance_km' => 'decimal:2',
        'elevation_gain_m' => 'integer',
        'estimated_time_min' => 'integer',
        'is_public' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(RouteFeature::class)->orderBy('sort_order');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(RouteRating::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RouteComment::class)->whereNull('parent_id')->with('replies')->latest();
    }

    public function groupRides(): HasMany
    {
        return $this->hasMany(GroupRide::class)->latest('ride_date');
    }

    public function upcomingRides(): HasMany
    {
        return $this->hasMany(GroupRide::class)
            ->where('ride_date', '>', now())
            ->whereIn('status', ['planned', 'confirmed'])
            ->orderBy('ride_date');
    }

    public function avgRating(): HasOne
    {
        return $this->hasOne(RouteAvgRating::class, 'route_id');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    public function scopeByDistanceRange($query, $min = null, $max = null)
    {
        if ($min) {
            $query->where('distance_km', '>=', $min);
        }
        if ($max) {
            $query->where('distance_km', '<=', $max);
        }

        return $query;
    }

    public function scopeByElevationRange($query, $min = null, $max = null)
    {
        if ($min) {
            $query->where('elevation_gain_m', '>=', $min);
        }
        if ($max) {
            $query->where('elevation_gain_m', '<=', $max);
        }

        return $query;
    }

    public function scopeWithFeatures($query, array $features)
    {
        return $query->whereHas('features', fn ($q) => $q->whereIn('feature_type', $features));
    }

    public function getAverageRatingAttribute(): float
    {
        return $this->avgRating?->avg_rating ?? 0;
    }

    public function getRatingCountAttribute(): int
    {
        return $this->avgRating?->rating_count ?? 0;
    }
}
