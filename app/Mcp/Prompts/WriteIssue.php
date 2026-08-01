<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Title('Write a user story')]
#[Description(
    'Turn a one-line request into a full user story: role/capability/benefit '
    .'statement, Gherkin acceptance criteria, out-of-scope list, definition of '
    .'done and an estimate, ready to create with `issues-create`.'
)]
class WriteIssue extends Prompt
{
    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'request',
                description: 'The one-line request, in the user\'s own words. E.g. "let people reset their password".',
                required: true,
            ),
            new Argument(
                name: 'team_key',
                description: 'Team key to create the issue under, e.g. "LAM". From `current` or `teams-list`.',
                required: false,
            ),
            new Argument(
                name: 'project_slug',
                description: 'Project (epic) the story belongs to. From `projects-list`.',
                required: false,
            ),
            new Argument(
                name: 'parent',
                description: 'Parent issue identifier (TEAMKEY-N) if this is a sub-issue of an existing story.',
                required: false,
            ),
            new Argument(
                name: 'workspace_slug',
                description: 'Workspace to operate in. Required when `current` reports more than one workspace.',
                required: false,
            ),
        ];
    }

    public function handle(Request $request): Response
    {
        $line = (string) $request->get('request', '<one-line request>');
        $teamKey = $request->get('team_key');
        $projectSlug = $request->get('project_slug');
        $parent = $request->get('parent');
        $workspaceSlug = $request->get('workspace_slug');

        $teamLine = $teamKey !== null
            ? "Create it under team `{$teamKey}`."
            : 'Ask which team key to use (`teams-list`) if it is not obvious from context.';

        $projectLine = $projectSlug !== null
            ? "Attach it to project `{$projectSlug}` via `project_slug`."
            : 'If an existing project (epic) covers this work, attach the story with `project_slug`; otherwise leave it in the backlog and say so.';

        $parentLine = $parent !== null
            ? "This is a sub-issue: set `parent` to `{$parent}`, and do not estimate it — the parent story carries the points."
            : 'If it turns out to be a task inside an existing story rather than a story of its own, set `parent` to that story instead of creating a standalone issue.';

        $workspaceLine = $workspaceSlug !== null
            ? "Pass `workspace_slug=\"{$workspaceSlug}\"` on every tool call."
            : 'Call `current` first; if it returns more than one workspace, ask which one and then pass `workspace_slug` on every subsequent call.';

        return Response::text(<<<PROMPT
            Turn this request into a complete user story:

            "{$line}"

            {$teamLine}
            {$projectLine}
            {$parentLine}
            {$workspaceLine}

            Read the resource `aims://guides/planning` and follow its story template
            and INVEST rules. Do not invent requirements: where the request is
            ambiguous, ask at most three sharp questions first, then write the story.

            Produce:

            * **Title** — specific and imperative, no ticket-speak.
            * **Description** — starting with
              "As a <role> I want <capability> so that <benefit>", where the role is a
              real user of this product and the benefit is the reason the work is
              worth doing.
            * **`## Acceptance criteria`** — Gherkin scenarios, one behaviour each:
              `Given` the context, `When` the action, `Then` an outcome that is
              observable by the user or by a test. At least the happy path plus one
              failure or edge case. Never two `When` steps in one scenario.
            * **`## Out of scope`** — what this story deliberately does not do.
            * **`## Definition of done`** — checklist: criteria pass, automated tests
              cover them, reviewed and merged, no regressions, docs updated when
              user-visible.
            * **`estimate`** — story points (1, 2, 3, 5, 8, 13). Above 8, split it into
              vertical slices and say so instead of creating one oversized story.
            * **Dependencies** — if the story needs other work first, name it and
              record the edge with `issues-link` (`blocks`) once both issues exist.

            Show the draft, then create it with `issues-create` on approval. Attach a
            short plan (`plan_content`) describing the intended approach so a later
            session can pick the work up without re-reading the codebase.
            PROMPT);
    }
}
