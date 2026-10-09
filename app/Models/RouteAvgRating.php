<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteAvgRating extends Model
{
    protected $table = 'route_avg_ratings';

    protected $primaryKey = 'route_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'rating_count' => 'integer',
        'avg_rating' => 'decimal:2',
        'stars_5' => 'integer',
        'stars_4' => 'integer',
        'stars_3' => 'integer',
        'stars_2' => 'integer',
        'stars_1' => 'integer',
    ];
}
