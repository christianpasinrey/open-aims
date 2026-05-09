<?php

declare(strict_types=1);

namespace App\Modules\Teams\Models;

use App\Core\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'key',
        'description',
        'icon',
        'color',
        'issue_counter',
        'private',
        'github_repo_full_name',
    ];

    protected $casts = [
        'private' => 'boolean',
        'issue_counter' => 'integer',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function workflowStates(): HasMany
    {
        return $this->hasMany(WorkflowState::class)->orderBy('position');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }
}
