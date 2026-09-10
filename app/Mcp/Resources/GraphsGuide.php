<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Uri('aims://guides/graphs')]
#[MimeType('text/markdown')]
#[Title('AIMS code graphs')]
#[Description(
    'What code graphs are, how they merge into the workspace map, the workflow to follow '
    .'(graphs-schema → graphs-attach planned → graphs-impact → graphs-attach implemented) and '
    .'how canonical keys are built. The full field and vocabulary reference is the graphs-schema tool.'
)]
class GraphsGuide extends Resource
{
    public function handle(): Response
    {
        return Response::text(<<<'MARKDOWN'
            # AIMS code graphs

            A graph is a set of **references to code and resources** — files, classes,
            functions, tables, columns, routes, events — plus the directed relations
            between them, attached to the project, milestone or issue that produced it.

            Nodes are **canonical per workspace**. Two graphs that mention the same
            file, class, function or resource build the same key and share the node,
            so independent graphs merge on their own into one map of the system. The
            map grows with every piece of work that is documented.

            ---

            ## Workflow

            1. `graphs-schema` — read the fields, vocabularies and example once per session.
            2. `graphs-map` — find existing canonical keys around the area you will touch.
            3. `graphs-attach stage=planned` — what the work intends to add, modify, remove or read.
            4. `graphs-impact` — who calls, reads, renders or tests what you change, and which
               open work touches the same nodes. Record risks and collisions on the issue.
            5. Code.
            6. `graphs-attach stage=implemented` with the **same title** and `ref` set to the
               commit — it replaces the planned graph and bumps its version.

            Use `stage=observed` to document existing code you explored but did not change.

            ---

            ## Keys

            | Level | Key |
            | --- | --- |
            | File | `{repo}:{file}` |
            | Class | `{repo}:{file}#{Class}` |
            | Function | `{repo}:{file}#{Class}::{function}` or `{repo}:{file}#{function}` |
            | Resource | `{repo}:resource:{type}:{name}` |

            Functions hang from their class (or file) and classes from their file
            automatically — never add edges for containment. Paths are relative to the
            repository root with `/`.

            ---

            ## What makes a graph useful

            - `change` on every reference and function: `read` never produces impact;
              `modified` and `removed` drive impact analysis.
            - Precise `signature` and `visibility`: a changed public signature affects
              every caller; a private one stays local.
            - `resources` for shared data: two pieces that never call each other still
              collide when they write the same column.
            - `tests` edges from test files to what they cover, so impact can list the
              tests to run.
            - Short, factual `description` (what it is), `purpose` (why it exists) and
              `summary` (what this work does to it).

            Every field and allowed value, with its meaning, is returned by `graphs-schema`.
            MARKDOWN);
    }
}
