<?php

declare(strict_types=1);

namespace App\Modules\Projects\Models;

use App\Core\Concerns\BelongsToWorkspace;
use App\Models\User;
use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Enums\ProjectState;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'description',
        'state',
        'lead_user_id',
        'start_date',
        'target_date',
        'color',
        'icon',
        'sort_order',
        'completed_at',
    ];

    protected $casts = [
        'state' => ProjectState::class,
        'start_date' => 'date',
        'target_date' => 'date',
        'completed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_user_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_teams');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }
}
