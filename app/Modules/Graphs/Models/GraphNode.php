<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Models;

use App\Core\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GraphNode extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'key',
        'key_hash',
        'kind',
        'repo',
        'parent_node_id',
        'file_path',
        'language',
        'namespace',
        'class_name',
        'class_type',
        'function_name',
        'signature',
        'visibility',
        'resource_type',
        'resource_name',
        'context',
        'role',
        'description',
        'purpose',
        'summary',
        'meta',
        'last_referenced_at',
    ];

    protected $casts = [
        'parent_node_id' => 'integer',
        'meta' => 'array',
        'last_referenced_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_node_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_node_id');
    }

    public function references(): HasMany
    {
        return $this->hasMany(GraphReference::class);
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(GraphEdge::class, 'source_node_id');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(GraphEdge::class, 'target_node_id');
    }
}
