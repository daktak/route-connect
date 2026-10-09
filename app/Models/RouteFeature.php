<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'feature_type',
        'feature_subtype',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public const FEATURE_TYPES = [
        'gravel' => 'Gravel Sections',
        'steep' => 'Steep Climbs',
        'technical' => 'Technical Singletrack',
        'scenic' => 'Scenic Viewpoints',
        'road' => 'Road Sections',
        'water' => 'Water Crossings',
        'cafe' => 'Cafe Stops',
        'shop' => 'Bike Shop Nearby',
        'custom' => 'Custom Feature',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function getLabelAttribute(): string
    {
        return self::FEATURE_TYPES[$this->feature_type] ?? ucfirst($this->feature_type);
    }

    public function getIconAttribute(): string
    {
        return match ($this->feature_type) {
            'gravel' => '🪨',
            'steep' => '📈',
            'technical' => '🚵',
            'scenic' => '🌄',
            'road' => '🛣️',
            'water' => '💧',
            'cafe' => '☕',
            'shop' => '🔧',
            'custom' => '⭐',
            default => '📍',
        };
    }
}
