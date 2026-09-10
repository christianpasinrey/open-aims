<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraphEdge extends Model
{
    protected $fillable = [
        'graph_id',
        'source_node_id',
        'target_node_id',
        'relation',
        'label',
    ];

    protected $casts = [
        'graph_id' => 'integer',
        'source_node_id' => 'integer',
        'target_node_id' => 'integer',
    ];

    public function graph(): BelongsTo
    {
        return $this->belongsTo(Graph::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(GraphNode::class, 'source_node_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(GraphNode::class, 'target_node_id');
    }
}
