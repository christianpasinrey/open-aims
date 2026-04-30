<?php

declare(strict_types=1);

namespace App\Modules\Teams\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowState extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'type',
        'color',
        'position',
        'description',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
