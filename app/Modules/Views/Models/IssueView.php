<?php

declare(strict_types=1);

namespace App\Modules\Views\Models;

use App\Core\Concerns\BelongsToWorkspace;
use App\Models\User;
use App\Modules\Teams\Models\Team;
use App\Modules\Views\Enums\ViewScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueView extends Model
{
    use BelongsToWorkspace;

    protected $table = 'issue_views';

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'owner_user_id',
        'scope',
        'team_id',
        'filters',
        'grouping',
        'sorting',
        'is_favorite',
        'sort_order',
    ];

    protected $casts = [
        'scope' => ViewScope::class,
        'filters' => 'array',
        'is_favorite' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
