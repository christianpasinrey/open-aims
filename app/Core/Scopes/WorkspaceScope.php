<?php

declare(strict_types=1);

namespace App\Core\Scopes;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a workspace-scoped model to the currently
 * resolved workspace bound at `app('current.workspace')`.
 *
 * If no workspace is bound (e.g. CLI commands, landlord operations),
 * the scope is a no-op.
 */
final class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('current.workspace')) {
            return;
        }

        /** @var Workspace|null $workspace */
        $workspace = app('current.workspace');
        if ($workspace === null) {
            return;
        }

        $builder->where(
            $model->qualifyColumn('workspace_id'),
            $workspace->getKey(),
        );
    }
}
