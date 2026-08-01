<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Uri('aims://guides/diagrams')]
#[MimeType('text/markdown')]
#[Title('AIMS plan rendering contract')]
#[Description(
    'The exact markup contract for plans attached to issues and projects: '
    .'diagrams and charts REQUIRE plan_format="html" (a markdown plan renders '
    .'no diagram at all), plan_libs=["mermaid"] is auto-initialised so you only '
    .'write <pre class="mermaid">, plan_libs=["chart"] injects Chart.js with no '
    .'init so your plan must supply its own <script>. Includes working mermaid '
    .'dependency-graph, mermaid gantt and Chart.js burndown examples. Read this '
    .'before writing any plan_content that contains a diagram or a chart.'
)]
class DiagramsGuide extends Resource
{
    public function handle(): Response
    {
        return Response::text(<<<'MARKDOWN'
            # AIMS plan rendering contract

            Plans are attached to issues and projects through the `plan_content`,
            `plan_format` and `plan_libs` parameters of `issues-create`,
            `issues-update`, `projects-create` and `projects-update`, and are read back
            in full by `issues-get` / `projects-get`. This document is the contract the
            renderer actually implements.

            ---

            ## 1. Diagrams require `plan_format="html"`

            **This is the single most common mistake.** The renderer returns early for
            any format other than `html`: a markdown plan is rendered as markdown and
            `plan_libs` is never injected, so a mermaid block inside a `md` plan
            silently renders as a code block — no diagram, no error.

            The API enforces the other half of the rule: `plan_libs` is *rejected*
            unless `plan_format="html"`.

            ```json
            {
              "plan_format": "html",
              "plan_libs": ["mermaid"],
              "plan_content": "<h1>Sprint 12</h1><pre class=\"mermaid\">graph LR\n  A-->B</pre>"
            }
            ```

            Rules of thumb:

            * Prose-only plan → `plan_format="md"`, no `plan_libs`.
            * Any diagram or chart → `plan_format="html"` and the matching lib. Write
              the prose as plain HTML (`<h2>`, `<p>`, `<ul>`, `<table>`); the renderer
              already ships default typography, table borders and code styling.

            ### What the renderer does with your HTML

            * If `plan_content` starts with `<!doctype` or `<html`, it is treated as a
              full document and the library `<script>` tags are injected before
              `</head>` (or right after `<body>` when there is no head).
            * Otherwise your HTML is treated as a body fragment and wrapped in a
              minimal document with a stylesheet and `<base target="_blank">`. Prefer
              the fragment form.
            * Unknown values in `plan_libs` are ignored; the accepted values are
              exactly `"mermaid"` and `"chart"`.
            * A plan larger than 200 KB is not rendered inline at all — a download link
              is shown instead. Keep plans well under that.

            ---

            ## 2. `plan_libs: ["mermaid"]` — diagrams, no script needed

            The renderer injects mermaid locally (no CDN) and **already runs**
            `mermaid.initialize({startOnLoad: true})` once the document is ready. Do
            not initialise it yourself, do not call `mermaid.run()`, do not add a
            `<script>`. You only write the block:

            ```html
            <pre class="mermaid">
            ...diagram source...
            </pre>
            ```

            Note the element is `<pre class="mermaid">` — **not** a
            ```` ```mermaid ```` fence, which only works in markdown and therefore
            never renders (see §1).

            ### 2.1 Dependency graph (the `blocks` DAG)

            ```html
            <h2>Dependency order</h2>
            <pre class="mermaid">
            graph LR
              LAM101["LAM-101 Password reset endpoint"]
              LAM102["LAM-102 Reset email template"]
              LAM103["LAM-103 Reset form UI"]
              LAM104["LAM-104 Rate limit reset requests"]

              LAM101 --> LAM103
              LAM102 --> LAM103
              LAM101 --> LAM104

              classDef done fill:#10b981,stroke:#065f46,color:#fff;
              class LAM101 done;
            </pre>
            ```

            An arrow `A --> B` reads "A blocks B", i.e. the same direction as an
            `issues-link` edge of type `blocks` from A to B.

            **Node ids must not contain `-`** — mermaid parses the hyphen as an edge
            token. Write the id as `LAM101` and put the real identifier in the label:
            `LAM101["LAM-101 Title"]`.

            ### 2.2 Sprint gantt

            ```html
            <h2>Sprint 12 schedule</h2>
            <pre class="mermaid">
            gantt
              title Sprint 12 — account recovery
              dateFormat YYYY-MM-DD
              axisFormat %d %b
              excludes weekends

              section Backend
              LAM-101 Reset endpoint    :done,   t1, 2026-06-01, 3d
              LAM-104 Rate limiting     :active, t2, after t1, 2d

              section Email
              LAM-102 Reset template    :        t3, 2026-06-01, 2d

              section Frontend
              LAM-103 Reset form        :        t4, after t1 t3, 4d

              section Milestones
              Sprint review             :milestone, m1, 2026-06-12, 0d
            </pre>
            ```

            Task ids (`t1`, `t3`) are what `after` references; the human text before the
            colon is free-form and may contain hyphens. `after t1 t3` waits for both.

            Other useful mermaid modes in a plan: `flowchart TD`, `sequenceDiagram`,
            `stateDiagram-v2`, `erDiagram`, `pie`.

            ---

            ## 3. `plan_libs: ["chart"]` — Chart.js, you must init it

            Chart.js v4 (UMD) is injected and exposes the global `Chart`. **There is no
            init step** — unlike mermaid, the renderer runs nothing for this lib, so a
            plan that only contains a `<canvas>` renders an empty box. Your plan must
            supply its own `<script>`.

            The library `<script>` tag is injected ahead of your content, so an inline
            script placed after the canvas can construct the chart immediately.

            ### 3.1 Burndown chart (complete, working)

            ```html
            <h2>Sprint 12 burndown — 34 points over 10 working days</h2>
            <div style="max-width:640px"><canvas id="burndown" height="220"></canvas></div>
            <script>
            (function () {
              var labels = ['Day 0','Day 1','Day 2','Day 3','Day 4','Day 5','Day 6','Day 7','Day 8','Day 9','Day 10'];
              var total = 34;
              var ideal = labels.map(function (_, i) {
                return Math.round((total - (total / (labels.length - 1)) * i) * 10) / 10;
              });
              var actual = [34, 34, 31, 28, 28, 22, 18, 13, 9, 4, 0];

              new Chart(document.getElementById('burndown'), {
                type: 'line',
                data: {
                  labels: labels,
                  datasets: [
                    {
                      label: 'Ideal',
                      data: ideal,
                      borderColor: '#94a3b8',
                      borderDash: [6, 4],
                      pointRadius: 0,
                      fill: false
                    },
                    {
                      label: 'Remaining points',
                      data: actual,
                      borderColor: '#6366f1',
                      backgroundColor: 'rgba(99,102,241,.15)',
                      tension: .25,
                      fill: true
                    }
                  ]
                },
                options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Story points' } },
                    x: { title: { display: true, text: 'Sprint day' } }
                  },
                  plugins: { legend: { position: 'bottom' } }
                }
              });
            })();
            </script>
            ```

            Same shape for `type: 'bar'` (velocity per cycle) or `type: 'doughnut'`
            (points by state). One `new Chart(...)` per `<canvas id>`; ids must be
            unique inside the plan.

            ### 3.2 Both libraries at once

            `"plan_libs": ["mermaid", "chart"]` is valid: mermaid still self-initialises
            and your Chart.js script still runs. Order in the array is irrelevant.

            ---

            ## 4. The sandbox

            Plans render inside an `<iframe sandbox="allow-scripts">`. That means:

            * **Scripts run** — Chart.js, mermaid and your own inline JavaScript all
              execute normally.
            * The frame has an **opaque origin**: no cookies, no AIMS session, no
              `localStorage`, no access to the parent page. It cannot call the AIMS API
              or any authenticated endpoint on your behalf. Never put a token in a plan
              expecting it to work — and never put a secret in a plan at all, since the
              plan body is returned verbatim by `issues-get` / `projects-get`.
            * Forms, popups and top-level navigation are not permitted, so keep plans
              to content: text, diagrams, charts, tables. Links may not open.
            * Render every value you want to show as literal content in
              `plan_content`; the plan cannot fetch live data.
            MARKDOWN);
    }
}
