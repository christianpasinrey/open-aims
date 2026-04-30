<?php

declare(strict_types=1);

namespace App\Core\Concerns;

use App\Core\Scopes\WorkspaceScope;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apply on every workspace-scoped Eloquent model.
 *
 * Adds the global WorkspaceScope so queries are automatically constrained
 * to the currently resolved workspace, and exposes the workspace relation.
 *
 * The model MUST have a `workspace_id` column.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function (self $model): void {
            if ($model->workspace_id === null && app()->bound('current.workspace')) {
                /** @var Workspace|null $workspace */
                $workspace = app('current.workspace');
                if ($workspace !== null) {
                    $model->workspace_id = $workspace->getKey();
                }
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
