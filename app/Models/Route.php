<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    public function avgRatingModel(): HasOne
    {
        return $this->hasOne(RouteAvgRating::class, 'route_id');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Select only the columns needed for list/map views, embedding a simplified
     * GeoJSON geometry instead of loading the full stored geometry and gpx_data.
     */
    public function scopeListColumns(Builder $query, float $tolerance = 0.0001): Builder
    {
        $tolerance = number_format($tolerance, 6, '.', '');

        return $query->select([
            'routes.id',
            'routes.user_id',
            'routes.name',
            'routes.description',
            'routes.distance_km',
            'routes.elevation_gain_m',
            'routes.estimated_time_min',
            'routes.difficulty',
            'routes.is_public',
            'routes.created_at',
            'routes.updated_at',
        ])->selectRaw("ST_AsGeoJSON(ST_SimplifyPreserveTopology(routes.geometry, {$tolerance})) as geometry_raw");
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

    public function getAvgRatingAttribute(): float
    {
        if (! is_null($this->attributes['avg_rating'] ?? null)) {
            return (float) $this->attributes['avg_rating'];
        }

        return (float) ($this->avgRatingModel?->avg_rating ?? 0);
    }

    public function getRatingCountAttribute(): int
    {
        return $this->avgRatingModel?->rating_count ?? 0;
    }

    /**
     * Return the route geometry as decoded GeoJSON.
     *
     * The PostGIS `geometry` column is stored as a LINESTRING for spatial
     * queries but is not read as GeoJSON by the connection. When a query
     * selects `ST_AsGeoJSON(geometry) as geometry` it is decoded directly;
     * otherwise the LineString is rebuilt from the stored `gpx_data`.
     */
    public function getGeometryAttribute(): ?array
    {
        $raw = $this->attributes['geometry'] ?? null;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['type'])) {
                return $decoded;
            }
        } elseif (is_array($raw)) {
            return $raw;
        }

        $segments = $this->gpx_data['tracks'][0]['segments'] ?? [];
        $coordinates = [];

        foreach ($segments as $segment) {
            foreach ($segment as $point) {
                $coordinates[] = [$point[0], $point[1]];
            }
        }

        return $coordinates ? ['type' => 'LineString', 'coordinates' => $coordinates] : null;
    }
}
