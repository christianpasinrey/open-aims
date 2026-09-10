<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Uri('aims://guides/documentation')]
#[MimeType('text/markdown')]
#[Title('AIMS documentation protocol')]
#[Description(
    'The mandatory way to document every project, milestone and issue in AIMS: which fields to fill, '
    .'the structure of the rich HTML plan (Mermaid diagrams, Chart.js charts), labels, acceptance '
    .'criteria, and when to attach a code graph (planned before coding, implemented when done). '
    .'Read this before creating or closing any work item.'
)]
class DocumentationGuide extends Resource
{
    public function handle(): Response
    {
        return Response::text(<<<'MARKDOWN'
            # AIMS documentation protocol

            Every project, milestone and issue is documented the same way, so any
            person or Claude session can pick the work up from AIMS alone, without
            reading the repository first. Follow it every time; do not skip steps
            because the change "is small" — small items get small plans, not none.

            ---

            ## 0. Before writing anything

            1. `current` → pass `workspace_slug` on every call.
            2. `labels-list` for the team. Create missing labels with `labels-ensure`
               (labels are per team and `issues-create` never creates them).
            3. `graphs-schema` before building any graph, and `graphs-map` to find the
               canonical keys that already exist so your graph links to them.

            ---

            ## 1. Project (epic)

            | Field | Rule |
            | --- | --- |
            | `goal` | Outcome in product terms that can be judged done / not done. |
            | `scope` | What is in **and** what is explicitly out. |
            | `lead`, `team_keys`, `target_date` | Always when known. |
            | plan | `plan_format="html"` with the skeleton in §4; `plan_libs` as needed. |
            | milestones | `projects-add-milestone` with description (deliverable + exit criterion) and `target_date`. |
            | graph | `graphs-attach` owner_type=project, `stage=planned`: the area the project will touch. |

            ---

            ## 2. Milestone

            - Description in markdown: the deliverable, the exit criterion and the
              issues it groups.
            - `target_date`.
            - Link issues with the `milestone` field of `issues-create` / `issues-update`.
            - When it closes: `graphs-attach` owner_type=milestone, `stage=implemented`,
              summarising what the milestone really delivered.

            ---

            ## 3. Issue (user story)

            | Field | Rule |
            | --- | --- |
            | title | Imperative and specific. |
            | description | "As a <role> I want <capability> so that <benefit>", context, out of scope. |
            | `acceptance_criteria` | Gherkin lines (Given/When/Then or Dado/Cuando/Entonces): happy path + at least one failure. |
            | `estimate` | Story points 1, 2, 3, 5, 8. Split anything bigger. |
            | `priority`, `labels` | One type label + up to two area labels (§6). |
            | `project_slug`, `milestone`, `parent` | Always set the ones that apply. |
            | dependencies | `issues-link type=blocks` for preconditions. |
            | plan | Files to touch, steps, tests. `md` for prose; `html` + Mermaid when there is a flow. |
            | graph (start) | `graphs-attach stage=planned` before coding, then `graphs-impact`; comment the risks and collisions. |
            | graph (end) | Same title, `stage=implemented`, `ref` = commit or merge; then transition and comment the commit / PR. |

            ---

            ## 4. HTML plan skeleton

            Plans with diagrams or charts MUST use `plan_format="html"` and the matching
            `plan_libs` (`mermaid`, `chart`). Exact markup rules: aims://guides/diagrams.

            ```html
            <h2>Context</h2>
            <p>Problem, evidence and constraints.</p>

            <h2>Decisions</h2>
            <table>
              <thead><tr><th>Topic</th><th>Chosen</th><th>Rejected</th><th>Why</th></tr></thead>
              <tbody><tr><td>…</td><td>…</td><td>…</td><td>…</td></tr></tbody>
            </table>

            <h2>Architecture</h2>
            <pre class="mermaid">
            flowchart LR
              UI["Page"] --> API["Controller"] --> DB[("table")]
            </pre>

            <h2>Data model</h2>
            <pre class="mermaid">
            erDiagram
              PROJECTS ||--o{ ISSUES : contains
            </pre>

            <h2>Delivery</h2>
            <pre class="mermaid">
            gantt
              dateFormat YYYY-MM-DD
              section Backend
              Schema :t1, 2026-09-14, 2d
            </pre>

            <h2>Numbers</h2>
            <canvas id="points" height="200"></canvas>
            <script>
              new Chart(document.getElementById('points'), {
                type: 'bar',
                data: { labels: ['M1', 'M2'], datasets: [{ label: 'Points', data: [8, 13] }] }
              });
            </script>
            ```

            Include "Data model" only when the schema changes and "Numbers" only when
            there is something to measure (estimates per milestone, burn-up, sizes).

            ---

            ## 5. Graph checklist

            - `stage` matches the moment: `planned` before code, `implemented` after,
              `observed` for existing code you only studied.
            - Every reference: `file`, `context`, `role`, `change`, `description`,
              `purpose`, `summary`, `functions`.
            - With a class: `namespace` and `class_type`. Every function: `signature`,
              `visibility`, `summary`.
            - Add `resources` for the tables, columns, routes and events involved.
            - Add edges for `calls`, `reads`, `writes`, `dispatches`, `listens`, `tests`…
              and reuse canonical keys from `graphs-map` / `graphs-get`.
            - Update a graph by sending the **same title**. Never create "v2" titles.

            ---

            ## 6. Labels

            - Type (exactly one): `feature`, `bug`, `refactor`, `docs`, `tests`, `infra`.
            - Area (zero to two): e.g. `mcp`, `ui`, `mobile`, `pwa`, `notifications`, `search`.
            - Keep the set small and stable; ensure it with `labels-ensure` before use.
            MARKDOWN);
    }
}
