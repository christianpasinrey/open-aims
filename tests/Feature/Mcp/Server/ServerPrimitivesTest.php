<?php

declare(strict_types=1);

use App\Mcp\Prompts\BreakdownEpic;
use App\Mcp\Prompts\PlanSprint;
use App\Mcp\Prompts\WriteIssue;
use App\Mcp\Resources\DiagramsGuide;
use App\Mcp\Resources\PlanningGuide;
use App\Mcp\Servers\AimsServer;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\FakeTransporter;

function aimsServerContext(): ServerContext
{
    $server = new AimsServer(new FakeTransporter);
    $server->start();

    return $server->createContext();
}

it('lists both guide resources', function () {
    $resources = aimsServerContext()->resources();

    expect($resources->map(fn (Resource $resource): string => $resource::class)->all())
        ->toContain(PlanningGuide::class)
        ->toContain(DiagramsGuide::class);

    expect($resources->map(fn (Resource $resource): string => $resource->uri())->all())
        ->toContain('aims://guides/planning')
        ->toContain('aims://guides/diagrams');
});

it('lists every prompt', function () {
    $prompts = aimsServerContext()->prompts();

    expect($prompts->map(fn (Prompt $prompt): string => $prompt::class)->all())
        ->toContain(PlanSprint::class)
        ->toContain(BreakdownEpic::class)
        ->toContain(WriteIssue::class);

    expect($prompts->map(fn (Prompt $prompt): string => $prompt->name())->all())
        ->toContain('plan-sprint')
        ->toContain('breakdown-epic')
        ->toContain('write-issue');
});

it('keeps auto-discovering module tools alongside the new primitives', function () {
    $tools = aimsServerContext()->tools();

    expect($tools->count())->toBeGreaterThan(10);
    expect($tools->map(fn ($tool): string => $tool->name())->all())
        ->toContain('current')
        ->toContain('issues-list');
});

it('keeps the pagination overrides so no tool is hidden behind a cursor', function () {
    $server = new AimsServer(new FakeTransporter);

    expect($server->defaultPaginationLength)->toBe(100)
        ->and($server->maxPaginationLength)->toBe(200);
});

it('returns non-empty markdown for every resource', function (string $resourceClass, string $uri) {
    $resource = app($resourceClass);

    expect($resource)->toBeInstanceOf(Resource::class);
    expect($resource->uri())->toBe($uri);
    expect($resource->mimeType())->toBe('text/markdown');

    $payload = $resource->handle()->content()->toResource($resource);

    expect($payload['mimeType'])->toBe('text/markdown')
        ->and($payload['uri'])->toBe($uri)
        ->and($payload['text'])->toBeString()
        ->and(mb_strlen($payload['text']))->toBeGreaterThan(500);
})->with([
    [PlanningGuide::class, 'aims://guides/planning'],
    [DiagramsGuide::class, 'aims://guides/diagrams'],
]);

it('reads each resource through the server', function (string $resourceClass, array $expected) {
    AimsServer::resource($resourceClass)
        ->assertOk()
        ->assertHasNoErrors()
        ->assertSee($expected);
})->with([
    [PlanningGuide::class, ['As a <role> I want <capability> so that <benefit>', 'Given', 'issues-link']],
    [DiagramsGuide::class, ['plan_format="html"', '<pre class="mermaid">', 'new Chart(']],
]);

it('renders each prompt', function (string $promptClass, array $arguments, array $expected) {
    AimsServer::prompt($promptClass, $arguments)
        ->assertOk()
        ->assertHasNoErrors()
        ->assertSee($expected);
})->with([
    [PlanSprint::class, ['team_key' => 'LAM', 'goal' => 'Users recover their account unaided'], ['LAM', 'Users recover their account unaided', 'cycles-create']],
    [BreakdownEpic::class, ['project_slug' => 'account-recovery'], ['account-recovery', 'parent', 'issues-link']],
    [WriteIssue::class, ['request' => 'let people reset their password'], ['let people reset their password', 'Acceptance criteria']],
]);

it('declares arguments on every prompt', function (string $promptClass, array $required) {
    /** @var Prompt $prompt */
    $prompt = app($promptClass);

    $arguments = collect($prompt->arguments());

    expect($arguments)->not->toBeEmpty();

    expect($arguments->where('required', true)->pluck('name')->all())->toBe($required);

    // Every argument documents itself for the client.
    $arguments->each(function ($argument): void {
        expect($argument->description)->not->toBe('');
    });
})->with([
    [PlanSprint::class, ['team_key', 'goal']],
    [BreakdownEpic::class, ['project_slug']],
    [WriteIssue::class, ['request']],
]);
