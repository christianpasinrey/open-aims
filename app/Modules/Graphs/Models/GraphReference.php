<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one graph says about one node: its own context, role, change and
 * texts. The node keeps only the latest values; this row keeps provenance.
 */
class GraphReference extends Model
{
    protected $fillable = [
        'graph_id',
        'graph_node_id',
        'context',
        'role',
        'change',
        'description',
        'purpose',
        'summary',
        'meta',
    ];

    protected $casts = [
        'graph_id' => 'integer',
        'graph_node_id' => 'integer',
        'meta' => 'array',
    ];

    public function graph(): BelongsTo
    {
        return $this->belongsTo(Graph::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(GraphNode::class, 'graph_node_id');
    }
}
