<?php

declare(strict_types=1);

namespace App\Modules\Projects\Models;

use App\Core\Concerns\BelongsToWorkspace;
use App\Models\User;
use App\Modules\Integrations\Github\Models\GithubLink;
use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Enums\ProjectState;
use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'description',
        'state',
        'priority',
        'lead_user_id',
        'creator_user_id',
        'start_date',
        'target_date',
        'color',
        'icon',
        'sort_order',
        'completed_at',
    ];

    protected $casts = [
        'state' => ProjectState::class,
        'priority' => 'integer',
        'start_date' => 'date',
        'target_date' => 'date',
        'completed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_teams');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'project_labels');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(ProjectResource::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class)->orderBy('occurred_at');
    }

    /**
     * @return MorphMany<GithubLink>
     */
    public function githubLinks(): MorphMany
    {
        return $this->morphMany(GithubLink::class, 'linkable');
    }
}
