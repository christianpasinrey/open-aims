<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Title('Plan a sprint')]
#[Description(
    'Given a team key and a sprint goal, produce a cycle plus estimated, '
    .'dependency-ordered user stories: creates the cycle, selects and sizes the '
    .'backlog stories that serve the goal, wires the blocks graph and checks the '
    .'committed points against capacity.'
)]
class PlanSprint extends Prompt
{
    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'team_key',
                description: 'Team key that owns the sprint, e.g. "LAM". From `current` or `teams-list`.',
                required: true,
            ),
            new Argument(
                name: 'goal',
                description: 'The sprint goal: one outcome-shaped sentence, not a task list.',
                required: true,
            ),
            new Argument(
                name: 'starts_at',
                description: 'Sprint start date, YYYY-MM-DD. Ask the user if unknown.',
                required: false,
            ),
            new Argument(
                name: 'ends_at',
                description: 'Sprint end date, YYYY-MM-DD. Ask the user if unknown.',
                required: false,
            ),
            new Argument(
                name: 'capacity',
                description: 'Capacity in story points for this sprint. Defaults to the completed points of the previous cycle (velocity).',
                required: false,
            ),
            new Argument(
                name: 'project_slug',
                description: 'Restrict candidate stories to this project (epic). From `projects-list`.',
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
        $teamKey = (string) $request->get('team_key', '<TEAM_KEY>');
        $goal = (string) $request->get('goal', '<sprint goal>');
        $startsAt = $request->get('starts_at');
        $endsAt = $request->get('ends_at');
        $capacity = $request->get('capacity');
        $projectSlug = $request->get('project_slug');
        $workspaceSlug = $request->get('workspace_slug');

        $window = $startsAt !== null && $endsAt !== null
            ? "The sprint runs from {$startsAt} to {$endsAt}."
            : 'The sprint dates were not given: ask the user for `starts_at` and `ends_at` (YYYY-MM-DD) before creating the cycle.';

        $capacityLine = $capacity !== null
            ? "Capacity is {$capacity} story points."
            : 'No capacity was given: derive it from the completed points of the previous cycle (`cycles-list` then `cycles-get`) and state the number you used.';

        $scopeLine = $projectSlug !== null
            ? "Draw candidate stories from project `{$projectSlug}` only."
            : 'Draw candidate stories from the team backlog, preferring the project(s) that serve the goal.';

        $workspaceLine = $workspaceSlug !== null
            ? "Pass `workspace_slug=\"{$workspaceSlug}\"` on every tool call."
            : 'Call `current` first; if it returns more than one workspace, ask which one and then pass `workspace_slug` on every subsequent call.';

        return Response::text(<<<PROMPT
            Plan the next sprint for team `{$teamKey}`.

            Sprint goal: {$goal}

            {$window}
            {$capacityLine}
            {$scopeLine}
            {$workspaceLine}

            Read the resource `aims://guides/planning` first and follow its sprint
            planning procedure and story conventions. Read `aims://guides/diagrams`
            before writing any plan that contains a diagram.

            Steps:

            1. Orient with `current`, then `teams-list` to confirm `{$teamKey}` exists.
            2. `cycles-list` for `{$teamKey}`; `cycles-get` the most recent completed
               cycle to read velocity.
            3. `issues-list` for candidate backlog stories. Reject anything that fails
               INVEST; rewrite weak titles/descriptions into the
               "As a <role> I want <capability> so that <benefit>" form with Gherkin
               acceptance criteria before scheduling it.
            4. Give every candidate an `estimate` in story points (1, 2, 3, 5, 8, 13).
               Split anything above 8 into vertical slices. Set it with
               `issues-update`.
            5. Build the dependency graph: create the missing edges with
               `issues-link {from: <blocker>, to: <blocked>, type: "blocks"}` and
               topologically sort the candidates. Drop any story
               whose blocker is not in the sprint, or pull the blocker in. Report any
               cycle in the graph as a modelling error to fix, do not work around it.
            6. Cut the list from the bottom of the dependency order until the summed
               estimates fit capacity.
            7. `cycles-create` the sprint (`team_key`, `starts_at`, `ends_at`, `name`),
               then attach the selected stories with `cycle_number`.
            8. Attach a plan to the driving project or lead issue with
               `plan_format="html"` and `plan_libs=["mermaid"]` containing: the sprint
               goal, the ordered story table (identifier, title, points, blocked-by),
               the committed total vs capacity, and a `graph LR` dependency diagram.

            Finish with a short summary: cycle `(team_key, number)`, committed points vs
            capacity, the execution order, and anything you deliberately left out.
            PROMPT);
    }
}
