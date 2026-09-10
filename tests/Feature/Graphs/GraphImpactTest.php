<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Graphs\Actions\IngestGraph;
use App\Modules\Graphs\Mcp\Tools\GraphsImpact;
use App\Modules\Graphs\Models\Graph;

if (! function_exists('impactReference')) {
    function impactReference(string $id, string $file, string $class, array $functions, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'file' => $file,
            'context' => 'backend',
            'role' => 'service',
            'change' => 'read',
            'namespace' => 'App',
            'class' => $class,
            'class_type' => 'class',
            'description' => "About {$class}",
            'purpose' => "Why {$class}",
            'summary' => "Work on {$class}",
            'functions' => array_map(static fn (array $function): array => array_merge([
                'visibility' => 'public',
                'summary' => 'Runs '.$function['name'],
            ], $function), $functions),
        ], $overrides);
    }

    function impactGraph(string $title, string $stage, array $references, array $edges = [], array $resources = []): array
    {
        return [
            'title' => $title,
            'summary' => 'Summary of '.$title,
            'stage' => $stage,
            'repo' => 'open-aims',
            'ref' => 'main',
            'references' => $references,
            'resources' => $resources,
            'edges' => $edges,
        ];
    }

    /**
     * Done issue X documents: two controllers call Billing::charge, BillingTest tests it,
     * charge writes invoices.total and Report::build reads it.
     * Open issue Y plans a change to RefundController and reads Billing::charge.
     * Open issue Z plans a new signature for Billing::charge.
     *
     * @return array<string,mixed>
     */
    function seedImpact(): array
    {
        $fix = makeWorkspaceFixture();
        $issueX = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Done'], ['title' => 'Existing billing']);
        $issueY = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Refunds']);
        $issueZ = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Capture charges']);
        $ingest = app(IngestGraph::class);

        $billing = static fn (array $overrides = [], string $signature = 'charge(int $amount): void', ?string $functionChange = null): array => impactReference(
            'svc',
            'app/Billing/Billing.php',
            'Billing',
            [array_filter(['name' => 'charge', 'signature' => $signature, 'change' => $functionChange])],
            $overrides,
        );

        $ingest($issueX, impactGraph('Existing', 'implemented', [
            $billing(),
            impactReference('checkout', 'app/Http/CheckoutController.php', 'CheckoutController', [['name' => 'store', 'signature' => 'store(Request $request): Response']], ['role' => 'controller']),
            impactReference('refund', 'app/Http/RefundController.php', 'RefundController', [['name' => 'store', 'signature' => 'store(Request $request): Response']], ['role' => 'controller']),
            impactReference('test', 'tests/Feature/BillingTest.php', 'BillingTest', [['name' => 'test_it_charges', 'signature' => 'test_it_charges(): void']], ['context' => 'tests', 'role' => 'test', 'namespace' => 'Tests\\Feature']),
            impactReference('report', 'app/Reports/Report.php', 'Report', [['name' => 'build', 'signature' => 'build(): array']]),
        ], [
            ['from' => 'checkout#store', 'to' => 'svc#charge', 'relation' => 'calls'],
            ['from' => 'refund#store', 'to' => 'svc#charge', 'relation' => 'calls'],
            ['from' => 'test#test_it_charges', 'to' => 'svc#charge', 'relation' => 'tests'],
            ['from' => 'svc#charge', 'to' => 'total', 'relation' => 'writes'],
            ['from' => 'report#build', 'to' => 'total', 'relation' => 'reads'],
        ], [[
            'id' => 'total',
            'type' => 'column',
            'name' => 'invoices.total',
            'description' => 'Invoice total.',
            'change' => 'read',
        ]]), null);

        $ingest($issueY, impactGraph('Refunds', 'planned', [
            $billing(),
            impactReference('refund', 'app/Http/RefundController.php', 'RefundController', [['name' => 'store', 'signature' => 'store(Request $request): Response']], ['role' => 'controller', 'change' => 'modified']),
        ]), null);

        $planned = $ingest($issueZ, impactGraph('Capture charges', 'planned', [
            $billing(['change' => 'modified'], 'charge(Money $amount, bool $capture): Charge'),
        ]), null);

        return compact('fix', 'issueX', 'issueY', 'issueZ', 'planned');
    }
}

it('lists what depends on the nodes a planned graph modifies', function () {
    $seed = seedImpact();

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsImpact::class, [
        'graph_id' => $seed['planned']->id,
        'depth' => 1,
    ])
        ->assertOk()
        ->assertSee([
            'CheckoutController::store',
            'RefundController::store',
            '"relation":"calls"',
            '"controller":2',
            'column:invoices.total',
        ])
        ->assertDontSee('Report::build');
});

it('follows data written by the change to its readers at depth 2', function () {
    $seed = seedImpact();

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsImpact::class, [
        'graph_id' => $seed['planned']->id,
        'depth' => 2,
    ])->assertOk()->assertSee(['Report::build', '"relation":"reads"']);
});

it('lists the tests that cover the affected code', function () {
    $seed = seedImpact();

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsImpact::class, [
        'graph_id' => $seed['planned']->id,
    ])->assertOk()->assertSee(['"tests_to_run"', 'BillingTest::test_it_charges', 'tests/Feature/BillingTest.php']);
});

it('reports open work touching the same nodes as collisions, but not finished work', function () {
    $seed = seedImpact();

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsImpact::class, [
        'graph_id' => $seed['planned']->id,
    ])
        ->assertOk()
        ->assertSee(['"collisions"', '"identifier":"ENG-'.$seed['issueY']->number.'"', 'Billing::charge'])
        ->assertDontSee('"identifier":"ENG-'.$seed['issueX']->number.'"');
});

it('flags signature changes against what other graphs recorded', function () {
    $seed = seedImpact();

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsImpact::class, [
        'graph_id' => $seed['planned']->id,
    ])->assertOk()->assertSee([
        '"signature_changes"',
        'charge(int $amount): void',
        'charge(Money $amount, bool $capture): Charge',
    ]);
});

it('produces no impact for references that are only read', function () {
    $seed = seedImpact();
    $reader = makeIssue($seed['fix']['team'], $seed['fix']['workspace'], $seed['fix']['states']['Todo']);
    $graph = app(IngestGraph::class)($reader, impactGraph('Just reading', 'observed', [
        impactReference('svc', 'app/Billing/Billing.php', 'Billing', [['name' => 'charge', 'signature' => 'charge(int $amount): void']]),
    ]), null);

    AimsServer::actingAs($seed['fix']['user'])->tool(GraphsImpact::class, [
        'graph_id' => $graph->id,
    ])->assertOk()->assertSee(['"seeds":[]', '"affected":[]']);
});

it('serves the impact to the web with node ids for highlighting', function () {
    $seed = seedImpact();

    $this->actingAs($seed['fix']['user'])
        ->withSession(['current_workspace_id' => $seed['fix']['workspace']->id])
        ->getJson(route('graphs.impact', ['graph' => $seed['planned']->id]))
        ->assertOk()
        ->assertJsonStructure([
            'graph' => ['id', 'title', 'stage'],
            'seeds' => [['id', 'key', 'label']],
            'affected' => [['id', 'key', 'label', 'kind', 'context', 'role', 'distance', 'via' => ['relation', 'from']]],
            'tests_to_run',
            'collisions',
            'signature_changes',
            'summary' => ['affected', 'by_context', 'by_role'],
        ]);
});

it('keeps impact inside the workspace', function () {
    $seed = seedImpact();
    $other = makeWorkspaceFixture();

    AimsServer::actingAs($other['user'])->tool(GraphsImpact::class, [
        'graph_id' => $seed['planned']->id,
    ])->assertHasErrors(['not found']);

    $this->actingAs($other['user'])
        ->withSession(['current_workspace_id' => $other['workspace']->id])
        ->getJson(route('graphs.impact', ['graph' => $seed['planned']->id]))
        ->assertNotFound();

    expect(Graph::withoutGlobalScopes()->count())->toBe(3);
});
