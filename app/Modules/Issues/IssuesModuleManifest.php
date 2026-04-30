<?php

declare(strict_types=1);

namespace App\Modules\Issues;

use App\Core\Contracts\ModuleManifest;
use App\Core\Contracts\ProvidesMcpTools;
use App\Modules\Issues\Mcp\Tools\InboxList;
use App\Modules\Issues\Mcp\Tools\IssuesArchive;
use App\Modules\Issues\Mcp\Tools\IssuesComment;
use App\Modules\Issues\Mcp\Tools\IssuesCreate;
use App\Modules\Issues\Mcp\Tools\IssuesDelete;
use App\Modules\Issues\Mcp\Tools\IssuesGet;
use App\Modules\Issues\Mcp\Tools\IssuesList;
use App\Modules\Issues\Mcp\Tools\IssuesTransition;
use App\Modules\Issues\Mcp\Tools\IssuesUpdate;

final class IssuesModuleManifest implements ModuleManifest, ProvidesMcpTools
{
    public function slug(): string
    {
        return 'issues';
    }

    public function label(): string
    {
        return 'Issues';
    }

    public function description(): string
    {
        return 'The unit of work — bug reports, features, tasks. Includes comments, labels, sub-issues and reactions.';
    }

    public function icon(): string
    {
        return 'circle-dot';
    }

    public function isMandatory(): bool
    {
        return true;
    }

    public function defaultLimits(): array
    {
        return [
            'max_open_issues_per_team' => 10000,
        ];
    }

    public function onboardingSteps(): array
    {
        return [];
    }

    public function dependencies(): array
    {
        return ['workspaces', 'teams'];
    }

    public function mcpTools(): array
    {
        return [
            InboxList::class,
            IssuesList::class,
            IssuesGet::class,
            IssuesCreate::class,
            IssuesUpdate::class,
            IssuesTransition::class,
            IssuesArchive::class,
            IssuesDelete::class,
            IssuesComment::class,
        ];
    }
}
