<?php

declare(strict_types=1);

namespace App\Modules\Views\Http\Controllers;

use App\Modules\Views\Models\IssueView;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves a saved view to a URL on /issues with the saved filters expanded
 * as query parameters. Does NOT render its own page.
 */
final class IssueViewShowController
{
    public function show(Request $request, int $view): RedirectResponse
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $userId = (int) ($request->user()?->getKey() ?? 0);

        $issueView = IssueView::query()
            ->where('workspace_id', $workspace->id)
            ->where('id', $view)
            ->first();

        if ($issueView === null) {
            throw new NotFoundHttpException('View not found.');
        }

        // Visibility check: personal views are private to their owner.
        if ($issueView->scope?->value === 'personal' && (int) $issueView->owner_user_id !== $userId) {
            throw new NotFoundHttpException('View not found.');
        }

        $params = $this->expandFilters($issueView);

        $qs = http_build_query($params);

        return redirect('/issues'.($qs !== '' ? '?'.$qs : ''));
    }

    /**
     * @return array<string, string>
     */
    private function expandFilters(IssueView $view): array
    {
        $params = [];
        $filters = is_array($view->filters) ? $view->filters : [];

        // Scope-derived team key takes precedence; otherwise honour explicit
        // team key embedded in filters.
        if ($view->scope?->value === 'team' && $view->team) {
            $params['team'] = (string) $view->team->key;
        } elseif (! empty($filters['team']) && is_string($filters['team'])) {
            $params['team'] = $filters['team'];
        }

        foreach (['assignee', 'state'] as $k) {
            if (! empty($filters[$k]) && is_string($filters[$k])) {
                $params[$k] = $filters[$k];
            }
        }
        if (isset($filters['priority']) && is_numeric($filters['priority'])) {
            $params['priority'] = (string) (int) $filters['priority'];
        }
        if (isset($filters['project']) && is_numeric($filters['project'])) {
            $params['project'] = (string) (int) $filters['project'];
        }
        if (! empty($filters['labels']) && is_array($filters['labels'])) {
            $ids = array_values(array_filter(
                array_map(static fn ($v) => is_numeric($v) ? (int) $v : null, $filters['labels']),
                static fn ($v) => $v !== null,
            ));
            if ($ids !== []) {
                $params['labels'] = implode(',', $ids);
            }
        }

        if ($view->grouping !== '' && $view->grouping !== 'status') {
            $params['group'] = (string) $view->grouping;
        }
        if ($view->sorting !== '' && $view->sorting !== 'priority') {
            $params['sort'] = (string) $view->sorting;
        }

        return $params;
    }
}
