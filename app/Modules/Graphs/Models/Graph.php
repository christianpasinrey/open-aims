<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Models;

use App\Core\Concerns\BelongsToWorkspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Graph extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'owner_type',
        'owner_id',
        'title',
        'summary',
        'stage',
        'repo',
        'ref',
        'version',
        'created_by_user_id',
    ];

    protected $casts = [
        'owner_id' => 'integer',
        'version' => 'integer',
        'created_by_user_id' => 'integer',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function references(): HasMany
    {
        return $this->hasMany(GraphReference::class);
    }

    public function edges(): HasMany
    {
        return $this->hasMany(GraphEdge::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
