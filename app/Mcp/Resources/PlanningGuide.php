<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Uri('aims://guides/planning')]
#[MimeType('text/markdown')]
#[Title('AIMS planning guide')]
#[Description(
    'How to plan work in AIMS: the object model as both a hierarchy '
    .'(initiative → project → issue → sub-issue) and a dependency graph '
    .'(blocks / blocked_by / related / duplicate_of), the Scrum mapping '
    .'(project = epic, issue = user story, cycle = sprint, estimate = story '
    .'points), the user story + Gherkin acceptance criteria template, and the '
    .'sprint planning procedure. Read this before creating projects, issues or '
    .'cycles.'
)]
class PlanningGuide extends Resource
{
    public function handle(): Response
    {
        return Response::text(<<<'MARKDOWN'
            # AIMS planning guide

            AIMS is a Linear-style tracker driven over MCP. Tool names are kebab-case
            (`issues-list`, never `issues.list`). Call `current` before any write: it
            returns every workspace you can reach, and when there is more than one you
            must pass `workspace_slug` explicitly on every subsequent call.

            ---

            ## 1. Two axes: hierarchy and dependency

            AIMS objects form a **hierarchy** (containment) *and* a **graph**
            (sequencing). They are different axes and answer different questions.

            ### 1.1 Hierarchy — "what is this part of?"

            ```
            initiative
              └── project
                    └── issue
                          └── sub-issue
            ```

            | Level | Tools | Attached by |
            | --- | --- | --- |
            | Initiative | `initiatives-list` | project ↔ initiative link |
            | Project | `projects-list`, `projects-get`, `projects-create` | — |
            | Issue | `issues-list`, `issues-get`, `issues-create`, `issues-update` | `project_slug` |
            | Sub-issue | same issue tools | `parent` (a `TEAMKEY-N` identifier) |

            A sub-issue is not a different object type — it is an issue whose `parent`
            points at another issue. Every node has at most one parent. Use the
            hierarchy to roll scope and progress up: sub-issues roll into their story,
            issues roll into their project, projects roll into their initiative.

            ### 1.2 Dependency — "what must happen first?"

            Edges live between issues and are written with `issues-link`
            (`from`, `to`, `type`, and `remove=true` to delete an edge):

            | Write `type` | Meaning | Ordering? |
            | --- | --- | --- |
            | `blocks` | `from` must be finished before `to` can start | yes |
            | `related` | symmetric, informational only | no |
            | `duplicate` | `from` duplicates `to`; close `from` | no |

            `issues-get` reads the same edges back as four lists:

            | Read key | Where it comes from |
            | --- | --- |
            | `blocks` | issues this one blocks (outgoing `blocks`) |
            | `blocked_by` | issues blocking this one — the same row seen from the other end |
            | `related` | symmetric edges |
            | `duplicate_of` | targets this issue duplicates |

            So `blocked_by` and `duplicate_of` are read-side names: you never write them,
            you produce them by writing the edge from the other issue. "LAM-9 is blocked
            by LAM-4" is written as `issues-link {from: "LAM-4", to: "LAM-9", type:
            "blocks"}`.

            `issues-link` never touches the hierarchy — parent/sub-issue is the `parent`
            field on `issues-create` / `issues-update`.

            The `blocks` edges form a DAG. That DAG — not the hierarchy — is what you
            topologically sort to get an execution order and to find the critical path.

            ### 1.3 Choosing the right axis

            * Use `parent` when the child is **part of** the work of the other issue
              (a task inside a story). Closing all children should mean the parent's
              work is done.
            * Use `blocks` when one issue is a **precondition** of the other. The two
              issues can live in different projects, different teams, or the same
              parent.
            * A parent never "blocks" its own children — containment already implies
              they are the same body of work. Do not add that edge; it makes the DAG
              noisy and breaks ordering.
            * Never model ordering with `parent`, and never model containment with
              `blocks`. Mixing the axes is the most common way a plan becomes
              unreadable.

            ---

            ## 2. Scrum mapping

            | Scrum | AIMS object | How it is addressed |
            | --- | --- | --- |
            | Product goal / epic-of-epics | Initiative | name |
            | Epic | Project | slug (from `projects-list`) |
            | User story | Issue | `TEAMKEY-N` (e.g. `LAM-275`) |
            | Task | Sub-issue | `TEAMKEY-N` with `parent` set |
            | Sprint | Cycle | `(team_key, number)` |
            | Story points | Issue field `estimate` | number |
            | Sprint backlog | Issues with `cycle_number = N` | — |
            | Product backlog | Issues with no cycle | — |

            Estimates are **story points**, not hours: relative size, Fibonacci-ish
            (1, 2, 3, 5, 8, 13). Anything above 8 is a signal to split the story. Both
            `issues-create` and `issues-update` accept `estimate`, e.g.
            `issues-update {identifier: "LAM-275", estimate: 5}`. The same two tools
            accept `parent` (a `TEAMKEY-N` identifier) and `cycle_number`.

            Estimate stories, not tasks. Sub-issues inherit their value from the parent
            story; double-counting sub-issue points corrupts velocity.

            ---

            ## 3. Writing a user story

            **Title**: imperative and specific, no ticket-speak — "Reset password from
            the login screen", not "Password stuff".

            **Description** (markdown, goes in `description`):

            ```markdown
            As a <role> I want <capability> so that <benefit>

            ## Acceptance criteria

            ### Scenario: <name>
            Given <the starting context>
            When <the action the user takes>
            Then <the observable outcome>

            ### Scenario: <edge case / failure>
            Given <context>
            When <action>
            Then <outcome>

            ## Out of scope
            - <what this story deliberately does not do>

            ## Definition of done
            - [ ] Acceptance criteria pass
            - [ ] Automated tests cover the criteria (happy path + at least one failure)
            - [ ] Code reviewed and merged
            - [ ] No regression in existing tests / linters clean
            - [ ] Docs or changelog updated when user-visible
            ```

            Rules for the criteria:

            * One `Given/When/Then` scenario per behaviour. `And` may extend any of the
              three steps; never chain two `When`s in one scenario.
            * `Then` must be observable by the user or by a test — never "the code is
              refactored".
            * At least two scenarios: the happy path and one failure/edge case.

            **INVEST** — check every story before you create it:

            * **I**ndependent — deliverable without waiting on another story, or the
              dependency is written explicitly as a `blocks` edge.
            * **N**egotiable — states the need, not the implementation.
            * **V**aluable — the `so that` clause names a real benefit to a real role.
            * **E**stimable — the team can size it; if not, spike it first.
            * **S**mall — fits comfortably inside one cycle (≤ 8 points).
            * **T**estable — the acceptance criteria can be executed as tests.

            A story that fails **S** gets split into several stories (vertical slices
            of value), *not* into sub-issues. Sub-issues are the implementation
            breakdown of one story: "add migration", "wire the endpoint", "write the
            component".

            ---

            ## 4. Writing a project (epic)

            `projects-create` enforces a quality gate: **`goal` and `scope` are
            mandatory** (the call is rejected without them unless you pass
            `skip_scrum=true`, which you should not do when planning).

            * **`goal`** — the outcome the project pursues, stated as a change in the
              product or the business, not as a list of tasks. It must be possible to
              say "done / not done" by looking at the product. Good: "Users can
              recover their account without contacting support." Bad: "Implement the
              password reset endpoints."
            * **`scope`** — the boundary: what is in and what is explicitly out. The
              out-list is the valuable half; it is what lets the team say no to work
              that does not serve the goal.

            Both are rendered into the project description under `## Goal` and
            `## Scope`. Also set `team_keys`, and `start_date` / `target_date` when
            known. Attach a plan (`plan_content`) unless you pass `skip_plan=true` —
            the plan is what a future session reads instead of re-deriving the design
            from the codebase.

            ---

            ## 5. Sprint planning procedure

            1. **Orient.** `current` → note the workspace slug and the team keys. Pass
               `workspace_slug` on every following call when the user reaches more than
               one workspace.
            2. **Look back.** `cycles-list` then `cycles-get` on the previous cycle to
               read the completed points — that is your velocity, and it is the only
               honest capacity input.
            3. **Create the sprint.** `cycles-create` with `team_key`, `starts_at`,
               `ends_at` (both `YYYY-MM-DD`) and a `name`. The number auto-increments
               per team; the cycle is then addressed as `(team_key, number)`.
            4. **Select candidate stories.** `issues-list` over the backlog, filtered to
               the project(s) that serve the sprint goal. Reject anything that fails
               INVEST.
            5. **Estimate.** Every candidate needs an `estimate`. Unestimable → replace
               it with a timeboxed spike story.
            6. **Order by dependency.** Read the `blocks` edges (create the missing ones
               with `issues-link`) and topologically sort the candidates. Then:
               * If a story is blocked by an issue *outside* the cycle, either pull the
                 blocker into the cycle or drop the story — never plan a story whose
                 blocker is not scheduled.
               * A cycle of `blocks` edges is a modelling error: break it by splitting
                 one of the issues.
               * Schedule blockers first; they are the critical path.
            7. **Check capacity.** Sum the `estimate` of the selected stories and
               compare against velocity. Over capacity → drop from the bottom of the
               dependency order, never from the middle.
            8. **Attach the stories.** `issues-update` (or `issues-create`) with
               `cycle_number = N`. Sub-issues follow their parent into the cycle.
            9. **Write the plan.** Attach a plan to the cycle's driving project or lead
               issue containing the sprint goal, the ordered story list with points,
               the dependency diagram and the burndown target. For diagram and chart
               markup read `aims://guides/diagrams` first — diagrams require
               `plan_format="html"`.

            ### Sprint goal

            One sentence, outcome-shaped, that survives losing any single story:
            "A user who forgot their password can get back into their account
            unaided." If dropping one story invalidates the goal, the goal is a task
            list, not a goal.
            MARKDOWN);
    }
}
