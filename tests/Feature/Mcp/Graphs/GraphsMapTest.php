<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Graphs\Actions\IngestGraph;
use App\Modules\Graphs\Mcp\Tools\GraphsMap;
use App\Modules\Projects\Models\Project;

if (! function_exists('mapReference')) {
    function mapReference(string $id, string $file, string $context, ?string $class, array $functions = [], string $namespace = 'App\\Billing'): array
    {
        return array_filter([
            'id' => $id,
            'file' => $file,
            'context' => $context,
            'role' => match ($context) {
                'frontend' => 'page',
                'tests' => 'test',
                default => 'service',
            },
            'change' => 'modified',
            'namespace' => $class !== null ? $namespace : null,
            'class' => $class,
            'class_type' => $class !== null ? 'class' : null,
            'description' => "About {$id}",
            'purpose' => "Why {$id}",
            'summary' => "Change to {$id}",
            'functions' => array_map(static fn (string $name): array => [
                'name' => $name,
                'signature' => "{$name}(): void",
                'visibility' => 'public',
                'summary' => "Runs {$name}",
            ], $functions),
        ], static fn ($value): bool => $value !== null);
    }

    function mapGraph(string $title, array $references, array $edges = [], array $resources = []): array
    {
        return [
            'title' => $title,
            'summary' => 'Summary of '.$title,
            'stage' => 'implemented',
            'repo' => 'open-aims',
            'ref' => 'main',
            'references' => $references,
            'resources' => $resources,
            'edges' => $edges,
        ];
    }

    /**
     * Issue A: page submit → Billing::charge, BillingTest tests Billing.
     * Issue B: Invoices::create → Billing::charge.
     * Project P: Report::build reads invoices.total.
     *
     * @return array{fix: array, issueA: mixed, issueB: mixed, project: Project}
     */
    function seedMap(): array
    {
        $fix = makeWorkspaceFixture();
        $issueA = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Checkout']);
        $issueB = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Invoices']);
        $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id, 'name' => 'Reporting']);
        $ingest = app(IngestGraph::class);

        $ingest($issueA, mapGraph('Checkout', [
            mapReference('svc', 'app/Billing/Billing.php', 'backend', 'Billing', ['charge']),
            mapReference('page', 'resources/js/pages/Checkout.vue', 'frontend', null, ['submit']),
            mapReference('test', 'tests/Feature/BillingTest.php', 'tests', 'BillingTest', [], 'Tests\\Feature'),
        ], [
            ['from' => 'page#submit', 'to' => 'svc#charge', 'relation' => 'calls'],
            ['from' => 'test', 'to' => 'svc', 'relation' => 'tests'],
        ]), null);

        $ingest($issueB, mapGraph('Invoices', [
            mapReference('svc', 'app/Billing/Billing.php', 'backend', 'Billing', ['charge']),
            mapReference('inv', 'app/Billing/Invoices.php', 'backend', 'Invoices', ['create']),
        ], [
            ['from' => 'inv#create', 'to' => 'svc#charge', 'relation' => 'calls'],
        ]), null);

        $ingest($project, mapGraph('Reporting', [
            mapReference('report', 'app/Reports/Report.php', 'backend', 'Report', ['build'], 'App\\Reports'),
        ], [
            ['from' => 'report#build', 'to' => 'total', 'relation' => 'reads'],
        ], [[
            'id' => 'total',
            'type' => 'column',
            'name' => 'invoices.total',
            'description' => 'Invoice total.',
            'change' => 'read',
        ]]), null);

        return compact('fix', 'issueA', 'issueB', 'project');
    }
}

it('describes a file with its classes, functions and the work that referenced it', function () {
    $seed = seedMap();

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsMap::class, [
        'mode' => 'node',
        'file' => 'app/Billing/Billing.php',
    ])
        ->assertOk()
        ->assertSee([
            '"kind":"file"',
            'Billing::charge',
            '"purpose":"Why svc"',
            '"identifier":"ENG-'.$seed['issueA']->number.'"',
            '"identifier":"ENG-'.$seed['issueB']->number.'"',
        ]);
});

it('lists the classes of a namespace', function () {
    $seed = seedMap();

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsMap::class, [
        'mode' => 'node',
        'namespace' => 'App\\Billing',
    ])
        ->assertOk()
        ->assertSee(['"class":"Billing"', '"class":"Invoices"'])
        ->assertDontSee('"class":"Report"');
});

it('finds neighbours up to the requested depth', function () {
    $seed = seedMap();
    $submit = 'open-aims:resources/js/pages/Checkout.vue#submit';

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsMap::class, [
        'mode' => 'neighbors',
        'key' => $submit,
        'depth' => 1,
    ])->assertOk()->assertSee('Billing::charge')->assertDontSee('Invoices::create');

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsMap::class, [
        'mode' => 'neighbors',
        'key' => $submit,
        'depth' => 2,
    ])->assertOk()->assertSee(['Billing::charge', 'Invoices::create', '"relation":"calls"']);
});

it('finds the shortest path between two nodes', function () {
    $seed = seedMap();

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsMap::class, [
        'mode' => 'path',
        'key' => 'open-aims:resources/js/pages/Checkout.vue#submit',
        'to' => 'open-aims:app/Billing/Invoices.php#Invoices::create',
    ])->assertOk()->assertSee(['"length":2', 'Billing::charge']);
});

it('ranks hotspots by the number of graphs that reference them', function () {
    $seed = seedMap();

    $response = AimsServer::actingAs($seed['fix']['user'])->tool(GraphsMap::class, [
        'mode' => 'hotspots',
        'context' => 'backend',
        'limit' => 3,
    ])->assertOk();

    $response->assertSee(['"graphs":2', 'Billing::charge'])->assertDontSee('Checkout.vue');
});

it('returns only what a project contributed as its subgraph', function () {
    $seed = seedMap();

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsMap::class, [
        'mode' => 'subgraph',
        'project_slug' => $seed['project']->slug,
    ])
        ->assertOk()
        ->assertSee(['Report::build', 'column:invoices.total', '"relation":"reads"'])
        ->assertDontSee('Billing::charge');
});

it('explains when a node or a path does not exist', function () {
    $seed = seedMap();

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsMap::class, [
        'mode' => 'node',
        'key' => 'open-aims:app/Nope.php',
    ])->assertHasErrors(['not found']);

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsMap::class, [
        'mode' => 'path',
        'key' => 'open-aims:resources/js/pages/Checkout.vue#submit',
        'to' => 'open-aims:app/Reports/Report.php#Report::build',
    ])->assertHasErrors(['No path']);

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsMap::class, [
        'mode' => 'everything',
    ])->assertHasErrors(['mode']);
});
