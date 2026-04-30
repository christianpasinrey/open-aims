<?php

declare(strict_types=1);

namespace App\Modules\Initiatives\Models;

use App\Core\Concerns\BelongsToWorkspace;
use App\Models\User;
use App\Modules\Initiatives\Enums\InitiativeState;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Initiative extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'state',
        'owner_user_id',
        'parent_initiative_id',
        'start_date',
        'target_date',
        'completed_at',
        'sort_order',
    ];

    protected $casts = [
        'state' => InitiativeState::class,
        'start_date' => 'date',
        'target_date' => 'date',
        'completed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_initiative_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_initiative_id')->orderBy('sort_order');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'initiative_projects')
            ->withPivot(['sort_order'])
            ->withTimestamps();
    }

    public function members(): HasMany
    {
        return $this->hasMany(InitiativeMember::class);
    }
}
