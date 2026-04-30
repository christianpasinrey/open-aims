<?php

declare(strict_types=1);

namespace App\Modules\Issues\Models;

use App\Core\Concerns\BelongsToWorkspace;
use App\Models\User;
use App\Modules\Issues\Enums\IssuePriority;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'team_id',
        'project_id',
        'cycle_id',
        'parent_issue_id',
        'number',
        'title',
        'description',
        'workflow_state_id',
        'priority',
        'assignee_user_id',
        'creator_user_id',
        'estimate',
        'due_date',
        'started_at',
        'completed_at',
        'canceled_at',
        'archived_at',
        'sort_order',
    ];

    protected $casts = [
        'priority' => IssuePriority::class,
        'due_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'canceled_at' => 'datetime',
        'archived_at' => 'datetime',
        'estimate' => 'float',
        'sort_order' => 'integer',
        'number' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workflowState(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_issue_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_issue_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('created_at');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'issue_labels');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(IssueSubscription::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function identifier(): string
    {
        $team = $this->team()->first();

        return $team !== null ? "{$team->key}-{$this->number}" : "ISSUE-{$this->number}";
    }
}
