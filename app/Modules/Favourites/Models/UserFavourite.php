<?php

declare(strict_types=1);

namespace App\Modules\Favourites\Models;

use App\Core\Concerns\BelongsToWorkspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A per-user starred item, scoped to a workspace.
 *
 * Despite using the BelongsToWorkspace trait (so the workspace relation +
 * automatic workspace_id assignment work), every query in the Favourites
 * module filters by `user_id` first — the table is logically user-scoped.
 */
class UserFavourite extends Model
{
    use BelongsToWorkspace;

    protected $table = 'user_favourites';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'kind',
        'target_type',
        'target_id',
        'label',
        'icon',
        'color',
        'href',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'target_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Polymorphic target — Issue, Project, Cycle, IssueView…
     * NULL for "page" favourites like Inbox.
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
