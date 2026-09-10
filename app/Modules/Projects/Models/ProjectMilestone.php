<?php

declare(strict_types=1);

namespace App\Modules\Projects\Models;

use App\Modules\Issues\Models\Issue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectMilestone extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'target_date',
        'sort_order',
        'completed_at',
    ];

    protected $casts = [
        'target_date' => 'date',
        'completed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // Milestones are soft-deleted, so the foreign key's nullOnDelete never
        // fires: release the issues here instead of leaving them pointing at a
        // milestone nobody can see.
        static::deleting(function (ProjectMilestone $milestone): void {
            Issue::query()
                ->withoutGlobalScopes()
                ->where('project_milestone_id', $milestone->getKey())
                ->toBase()
                ->update(['project_milestone_id' => null]);
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class, 'project_milestone_id');
    }
}
