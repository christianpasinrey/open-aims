<?php

declare(strict_types=1);

use App\Modules\Graphs\Actions\IngestGraph;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;

if (! function_exists('apiGraph')) {
    function apiGraph(string $title, array $references, array $edges = []): array
    {
        return [
            'title' => $title,
            'summary' => 'Summary of '.$title,
            'stage' => 'implemented',
            'repo' => 'open-aims',
            'ref' => 'main',
            'references' => $references,
            'resources' => [],
            'edges' => $edges,
        ];
    }

    function apiReference(string $id, string $file, string $context, ?string $class = null, array $functions = []): array
    {
        return array_filter([
            'id' => $id,
            'file' => $file,
            'context' => $context,
            'role' => $context === 'frontend' ? 'component' : 'service',
            'change' => 'modified',
            'namespace' => $class !== null ? 'App\\Services' : null,
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

    /** Billing service called from a frontend page. */
    function billingGraph(string $title = 'Billing'): array
    {
        return apiGraph($title, [
            apiReference('svc', 'app/Services/Billing.php', 'backend', 'Billing', ['charge']),
            apiReference('page', 'resources/js/pages/Billing.vue', 'frontend', null, ['submit']),
        ], [['from' => 'page#submit', 'to' => 'svc#charge', 'relation' => 'calls']]);
    }
}

beforeEach(function () {
    $this->fix = makeWorkspaceFixture();
    $this->as = fn () => $this->actingAs($this->fix['user'])
        ->withSession(['current_workspace_id' => $this->fix['workspace']->id]);
});

it('returns the graphs of an issue ready for the viewer', function () {
    $issue = makeIssue($this->fix['team'], $this->fix['workspace'], $this->fix['states']['Todo']);
    app(IngestGraph::class)($issue, billingGraph(), null);

    $response = ($this->as)()
        ->getJson(route('graphs.owner', ['type' => 'issue', 'id' => $issue->id]))
        ->assertOk();

    $graph = $response->json('graphs.0');
    $kinds = collect($graph['nodes'])->pluck('kind')->sort()->values()->all();
    $relations = collect($graph['links'])->pluck('relation')->sort()->values()->all();
    $charge = collect($graph['nodes'])->firstWhere('function', 'charge');

    expect($response->json('owner.type'))->toBe('issue')
        ->and($graph['title'])->toBe('Billing')
        ->and($graph['stage'])->toBe('implemented')
        ->and($kinds)->toBe(['class', 'file', 'file', 'function', 'function'])
        ->and($relations)->toBe(['calls', 'contains', 'contains', 'contains'])
        ->and($charge['label'])->toBe('Billing::charge')
        ->and($charge['change'])->toBe('modified')
        ->and($charge['summary'])->toBe('Runs charge')
        ->and($charge['context'])->toBe('backend');
});

it('merges every graph of the workspace into the map with edge weights', function () {
    $issue = makeIssue($this->fix['team'], $this->fix['workspace'], $this->fix['states']['Todo']);
    $project = Project::factory()->create(['workspace_id' => $this->fix['workspace']->id]);
    app(IngestGraph::class)($issue, billingGraph(), null);
    app(IngestGraph::class)($project, billingGraph('Billing roadmap'), null);

    $response = ($this->as)()->getJson(route('graphs.map'))->assertOk();

    $calls = collect($response->json('links'))->firstWhere('relation', 'calls');
    $charge = collect($response->json('nodes'))->firstWhere('function', 'charge');

    expect($response->json('nodes'))->toHaveCount(5)
        ->and($calls['weight'])->toBe(2)
        ->and($charge['graphs'])->toBe(2)
        ->and($response->json('stats.graphs'))->toBe(2);
});

it('filters the map by context', function () {
    $issue = makeIssue($this->fix['team'], $this->fix['workspace'], $this->fix['states']['Todo']);
    app(IngestGraph::class)($issue, billingGraph(), null);

    $response = ($this->as)()->getJson(route('graphs.map', ['context' => 'frontend']))->assertOk();

    expect(collect($response->json('nodes'))->pluck('context')->unique()->values()->all())->toBe(['frontend'])
        ->and($response->json('nodes'))->toHaveCount(2)
        ->and(collect($response->json('links'))->pluck('relation')->all())->toBe(['contains']);
});

it('filters the map by project, including its milestones and issues', function () {
    $project = Project::factory()->create(['workspace_id' => $this->fix['workspace']->id]);
    $other = Project::factory()->create(['workspace_id' => $this->fix['workspace']->id]);
    $milestone = ProjectMilestone::create(['project_id' => $project->id, 'name' => 'M1', 'sort_order' => 0]);
    $issue = makeIssue($this->fix['team'], $this->fix['workspace'], $this->fix['states']['Todo'], [
        'project_id' => $project->id,
    ]);
    $ingest = app(IngestGraph::class);

    $ingest($project, apiGraph('Project', [apiReference('a', 'app/ProjectLevel.php', 'backend')]), null);
    $ingest($milestone, apiGraph('Milestone', [apiReference('b', 'app/MilestoneLevel.php', 'backend')]), null);
    $ingest($issue, apiGraph('Issue', [apiReference('c', 'app/IssueLevel.php', 'backend')]), null);
    $ingest($other, apiGraph('Other', [apiReference('d', 'app/OtherProject.php', 'backend')]), null);

    $files = collect(($this->as)()
        ->getJson(route('graphs.map', ['project' => $project->slug]))
        ->assertOk()
        ->json('nodes'))->pluck('file')->sort()->values()->all();

    expect($files)->toBe(['app/IssueLevel.php', 'app/MilestoneLevel.php', 'app/ProjectLevel.php']);
});

it('does not expose owners from another workspace or unknown owner types', function () {
    $outsider = makeWorkspaceFixture();
    $foreignIssue = makeIssue($outsider['team'], $outsider['workspace'], $outsider['states']['Todo']);
    app(IngestGraph::class)($foreignIssue, billingGraph(), null);

    app()->instance('current.workspace', $this->fix['workspace']);

    ($this->as)()->getJson(route('graphs.owner', ['type' => 'issue', 'id' => $foreignIssue->id]))->assertNotFound();
    ($this->as)()->getJson('/graphs/owners/cycle/1')->assertNotFound();
});
