<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Graphs\Actions\IngestGraph;
use App\Modules\Graphs\Mcp\Tools\GraphsAttach;
use App\Modules\Graphs\Mcp\Tools\GraphsSchema;
use App\Modules\Graphs\Support\GraphSchema;
use App\Modules\Graphs\Support\GraphVocabulary;

it('explains every part of the schema by default', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(GraphsSchema::class)
        ->assertOk()
        ->assertSee([
            '"graph"',
            '"reference"',
            '"required_with_class"',
            '"function"',
            '"resource"',
            '"edge"',
            '"keys"',
            '"vocabulary"',
            '"limits"',
            '"example"',
            '"planned"',
            '"mcp_tool"',
            '"routes_to"',
        ]);
});

it('returns a single topic when asked', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(GraphsSchema::class, ['topic' => 'relations'])
        ->assertOk()
        ->assertSee(['"relations"', '"calls"', '"migrates"'])
        ->assertDontSee(['"contexts"', '"example"']);
});

it('rejects an unknown topic', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(GraphsSchema::class, ['topic' => 'everything'])
        ->assertHasErrors(['topic']);
});

it('defines every vocabulary value and nothing else', function (array $definitions, array $values) {
    expect(array_keys($definitions))->toBe($values);
})->with([
    'stages' => [GraphSchema::STAGES, GraphVocabulary::STAGES],
    'contexts' => [GraphSchema::CONTEXTS, GraphVocabulary::CONTEXTS],
    'roles' => [GraphSchema::ROLES, GraphVocabulary::ROLES],
    'changes' => [GraphSchema::CHANGES, GraphVocabulary::CHANGES],
    'class types' => [GraphSchema::CLASS_TYPES, GraphVocabulary::CLASS_TYPES],
    'visibilities' => [GraphSchema::VISIBILITIES, GraphVocabulary::VISIBILITIES],
    'side effects' => [GraphSchema::SIDE_EFFECTS, GraphVocabulary::SIDE_EFFECTS],
    'resource types' => [GraphSchema::RESOURCE_TYPES, GraphVocabulary::RESOURCE_TYPES],
    'relations' => [GraphSchema::RELATIONS, GraphVocabulary::RELATIONS],
]);

it('ships an example that ingests as is', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    $graph = app(IngestGraph::class)($issue, GraphSchema::example(), null);

    expect($graph->version)->toBe(1)
        ->and($graph->edges()->count())->toBe(3);

    AimsServer::actingAs($fix['user'])->tool(GraphsAttach::class, [
        'owner_type' => 'issue',
        'owner' => 'ENG-'.$issue->number,
    ] + GraphSchema::example())->assertOk()->assertSee('"version":2');
});
