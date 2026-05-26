<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Plan extends Model
{
    protected $fillable = [
        'planable_type', 'planable_id', 'format', 'content',
        'libs', 'version', 'is_current', 'created_by_user_id',
    ];

    protected $casts = [
        'libs' => 'array',
        'is_current' => 'bool',
        'version' => 'int',
    ];

    public function planable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }
}
