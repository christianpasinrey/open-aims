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

    /** A graph that only references one file, to tell graphs apart by title. */
    function fileGraph(string $title, string $file): array
    {
        return apiGraph($title, [apiReference('file', $file, 'backend')]);
    }
}

beforeEach(function () {
    $this->fix = makeWorkspaceFixture();
    $this->as = fn () => $this->actingAs($this->fix['user'])
        ->withSession(['current_workspace_id' => $this->fix['workspace']->id]);
});

it('lists the graphs of an issue without their nodes', function () {
    $issue = makeIssue($this->fix['team'], $this->fix['workspace'], $this->fix['states']['Todo']);
    app(IngestGraph::class)($issue, billingGraph(), null);

    $response = ($this->as)()
        ->getJson(route('graphs.owner', ['type' => 'issue', 'id' => $issue->id]))
        ->assertOk();

    $graph = $response->json('graphs.0');

    expect($response->json('owner.type'))->toBe('issue')
        ->and($response->json('graphs'))->toHaveCount(1)
        ->and($graph)->not->toHaveKeys(['nodes', 'links'])
        ->and($graph['title'])->toBe('Billing')
        ->and($graph['stage'])->toBe('implemented')
        ->and($graph['own'])->toBeTrue()
        ->and($graph['owner']['type'])->toBe('issue')
        ->and($graph['owner']['url'])->toBe('/issues/'.$this->fix['team']->key.'-'.$issue->number);
});

it('returns one graph ready for the viewer', function () {
    $issue = makeIssue($this->fix['team'], $this->fix['workspace'], $this->fix['states']['Todo']);
    $stored = app(IngestGraph::class)($issue, billingGraph(), null);

    $response = ($this->as)()
        ->getJson(route('graphs.show', ['graph' => $stored->id]))
        ->assertOk();

    $kinds = collect($response->json('nodes'))->pluck('kind')->sort()->values()->all();
    $relations = collect($response->json('links'))->pluck('relation')->sort()->values()->all();
    $charge = collect($response->json('nodes'))->firstWhere('function', 'charge');

    expect($response->json('id'))->toBe($stored->id)
        ->and($response->json('title'))->toBe('Billing')
        ->and($response->json('version'))->toBe(1)
        ->and($kinds)->toBe(['class', 'file', 'file', 'function', 'function'])
        ->and($relations)->toBe(['calls', 'contains', 'contains', 'contains'])
        ->and($charge['label'])->toBe('Billing::charge')
        ->and($charge['change'])->toBe('modified')
        ->and($charge['summary'])->toBe('Runs charge')
        ->and($charge['context'])->toBe('backend');
});

it('lists the graphs of a project with those of its milestones and issues, its own first', function () {
    $project = Project::factory()->create(['workspace_id' => $this->fix['workspace']->id]);
    $other = Project::factory()->create(['workspace_id' => $this->fix['workspace']->id]);
    $milestone = ProjectMilestone::create(['project_id' => $project->id, 'name' => 'M1', 'sort_order' => 0]);
    $issue = makeIssue($this->fix['team'], $this->fix['workspace'], $this->fix['states']['Todo'], [
        'project_id' => $project->id,
    ]);
    $foreignIssue = makeIssue($this->fix['team'], $this->fix['workspace'], $this->fix['states']['Todo'], [
        'project_id' => $other->id,
    ]);
    $ingest = app(IngestGraph::class);

    $ingest($issue, fileGraph('Issue graph', 'app/IssueLevel.php'), null);
    $ingest($milestone, fileGraph('Milestone graph', 'app/MilestoneLevel.php'), null);
    $ingest($project, fileGraph('Project graph', 'app/ProjectLevel.php'), null);
    $ingest($other, fileGraph('Other project graph', 'app/OtherProject.php'), null);
    $ingest($foreignIssue, fileGraph('Other issue graph', 'app/OtherIssue.php'), null);

    $graphs = collect(($this->as)()
        ->getJson(route('graphs.owner', ['type' => 'project', 'id' => $project->id]))
        ->assertOk()
        ->json('graphs'));

    expect($graphs->pluck('title')->all())->toBe(['Project graph', 'Milestone graph', 'Issue graph'])
        ->and($graphs->pluck('own')->all())->toBe([true, false, false])
        ->and($graphs->pluck('owner.type')->all())->toBe(['project', 'milestone', 'issue'])
        ->and($graphs->last()['owner']['identifier'])->toBe($this->fix['team']->key.'-'.$issue->number);
});

it('lists the graphs of a project that only has graphs on its issues', function () {
    $project = Project::factory()->create(['workspace_id' => $this->fix['workspace']->id]);
    $first = makeIssue($this->fix['team'], $this->fix['workspace'], $this->fix['states']['Todo'], [
        'project_id' => $project->id,
    ]);
    $second = makeIssue($this->fix['team'], $this->fix['workspace'], $this->fix['states']['Todo'], [
        'project_id' => $project->id,
    ]);
    $ingest = app(IngestGraph::class);

    $ingest($second, fileGraph('Second', 'app/Second.php'), null);
    $ingest($first, fileGraph('First', 'app/First.php'), null);

    $graphs = collect(($this->as)()
        ->getJson(route('graphs.owner', ['type' => 'project', 'id' => $project->id]))
        ->assertOk()
        ->json('graphs'));

    expect($graphs->pluck('title')->all())->toBe(['First', 'Second'])
        ->and($graphs->pluck('own')->unique()->all())->toBe([false]);
});

it('lists the graphs of a milestone with those of its issues only', function () {
    $project = Project::factory()->create(['workspace_id' => $this->fix['workspace']->id]);
    $milestone = ProjectMilestone::create(['project_id' => $project->id, 'name' => 'M1', 'sort_order' => 0]);
    $otherMilestone = ProjectMilestone::create(['project_id' => $project->id, 'name' => 'M2', 'sort_order' => 1]);
    $inMilestone = makeIssue($this->fix['team'], $this->fix['workspace'], $this->fix['states']['Todo'], [
        'project_id' => $project->id,
        'project_milestone_id' => $milestone->id,
    ]);
    $elsewhere = makeIssue($this->fix['team'], $this->fix['workspace'], $this->fix['states']['Todo'], [
        'project_id' => $project->id,
        'project_milestone_id' => $otherMilestone->id,
    ]);
    $ingest = app(IngestGraph::class);

    $ingest($project, fileGraph('Project graph', 'app/ProjectLevel.php'), null);
    $ingest($milestone, fileGraph('Milestone graph', 'app/MilestoneLevel.php'), null);
    $ingest($inMilestone, fileGraph('Issue graph', 'app/IssueLevel.php'), null);
    $ingest($elsewhere, fileGraph('Elsewhere graph', 'app/Elsewhere.php'), null);

    $graphs = collect(($this->as)()
        ->getJson(route('graphs.owner', ['type' => 'milestone', 'id' => $milestone->id]))
        ->assertOk()
        ->json('graphs'));

    expect($graphs->pluck('title')->all())->toBe(['Milestone graph', 'Issue graph'])
        ->and($graphs->pluck('own')->all())->toBe([true, false]);
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

it('does not expose owners or graphs from another workspace, or unknown owner types', function () {
    $outsider = makeWorkspaceFixture();
    $foreignIssue = makeIssue($outsider['team'], $outsider['workspace'], $outsider['states']['Todo']);
    $foreignGraph = app(IngestGraph::class)($foreignIssue, billingGraph(), null);

    app()->instance('current.workspace', $this->fix['workspace']);

    ($this->as)()->getJson(route('graphs.owner', ['type' => 'issue', 'id' => $foreignIssue->id]))->assertNotFound();
    ($this->as)()->getJson(route('graphs.show', ['graph' => $foreignGraph->id]))->assertNotFound();
    ($this->as)()->getJson('/graphs/owners/cycle/1')->assertNotFound();
});
