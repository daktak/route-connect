<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouteComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'user_id',
        'parent_id',
        'content',
        'is_edited',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RouteComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(RouteComment::class, 'parent_id')->with('user')->latest();
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }
}
