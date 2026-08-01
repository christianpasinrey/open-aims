<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Title('Break down an epic')]
#[Description(
    'Given a project slug (the epic), break it into user stories and sub-issues '
    .'with Gherkin acceptance criteria, estimates, parent links and the `blocks` '
    .'dependency edges between them.'
)]
class BreakdownEpic extends Prompt
{
    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'project_slug',
                description: 'Slug of the project (epic) to break down. From `projects-list`.',
                required: true,
            ),
            new Argument(
                name: 'team_key',
                description: 'Team key to create the issues under, e.g. "LAM". Defaults to the project\'s first team.',
                required: false,
            ),
            new Argument(
                name: 'focus',
                description: 'Optional slice of the epic to break down, when the whole epic is too large for one pass.',
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
        $projectSlug = (string) $request->get('project_slug', '<project-slug>');
        $teamKey = $request->get('team_key');
        $focus = $request->get('focus');
        $workspaceSlug = $request->get('workspace_slug');

        $teamLine = $teamKey !== null
            ? "Create the issues under team `{$teamKey}`."
            : 'Read the project\'s teams from `projects-get` and create the issues under the team that owns the work; ask if it is ambiguous.';

        $focusLine = $focus !== null
            ? "Limit this breakdown to: {$focus}. Note explicitly what remains unbroken."
            : 'Break down the whole epic. If it yields more than ~12 stories, break down the highest-value slice first and say what is left.';

        $workspaceLine = $workspaceSlug !== null
            ? "Pass `workspace_slug=\"{$workspaceSlug}\"` on every tool call."
            : 'Call `current` first; if it returns more than one workspace, ask which one and then pass `workspace_slug` on every subsequent call.';

        return Response::text(<<<PROMPT
            Break the epic `{$projectSlug}` down into deliverable work.

            {$teamLine}
            {$focusLine}
            {$workspaceLine}

            Read the resource `aims://guides/planning` first and follow its story
            template, INVEST rules and hierarchy-vs-dependency rules.

            Steps:

            1. `projects-get` `{$projectSlug}` and read its goal, scope and current
               plan. Everything you create must serve the goal and stay inside the
               scope; anything outside it is reported, not created.
            2. `issues-list` filtered to the project so you do not duplicate stories
               that already exist. Extend existing stories instead of forking them.
            3. Propose the story set as vertical slices of user value, each sized
               <= 8 points. For every story write:
               * a specific imperative title;
               * a description starting with
                 "As a <role> I want <capability> so that <benefit>";
               * `## Acceptance criteria` with at least two Gherkin scenarios
                 (Given/When/Then) — the happy path and one failure or edge case;
               * `## Definition of done`;
               * an `estimate` in story points.
            4. For each story, add the implementation tasks as **sub-issues** — issues
               whose `parent` is the story identifier. Tasks are not estimated; the
               story carries the points.
            5. Wire the **dependency** edges with
               `issues-link {from: <blocker>, to: <blocked>, type: "blocks"}`. Do not
               add an edge between a parent and its own sub-issue — containment already
               implies it. Keep the graph acyclic.
            6. Create everything with `issues-create` (`team_key`, `title`,
               `description`, `project_slug`, `parent` for sub-issues), then set
               estimates and links.
            7. Attach or update the project plan with `plan_format="html"` and
               `plan_libs=["mermaid"]` showing the story map and a `graph LR`
               dependency diagram — see `aims://guides/diagrams` for the markup.

            Before creating anything, show the proposed story list (title, points,
            blocked-by) and get confirmation. Finish with the created identifiers, the
            total points, and the suggested execution order.
            PROMPT);
    }
}
