<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use Laravel\Mcp\Server\Transport\FakeTransporter;

function aimsInstructions(): string
{
    return (new AimsServer(new FakeTransporter))->createContext()->instructions;
}

it('never cites a dot-notation tool name', function () {
    $instructions = aimsInstructions();

    // The names that used to be advertised and never existed.
    foreach (['issues.list', 'workspace.current', 'projects.list', 'cycles.get', 'inbox.list'] as $ghost) {
        expect($instructions)->not->toContain($ghost);
    }

    // Nothing dotted at all: `<word>.<word>` is never a valid AIMS tool name.
    expect($instructions)->not->toMatch('/\b[a-z]+\.[a-z]+\b(?!\/)/');
});

it('names real kebab-case tools', function () {
    $instructions = aimsInstructions();

    foreach (['current', 'workspaces-list', 'issues-list', 'issues-create', 'issues-link', 'projects-list', 'cycles-create', 'inbox-list', 'search'] as $tool) {
        expect($instructions)->toContain($tool);
    }
});

it('makes orientation and explicit workspace scoping non-negotiable', function () {
    $instructions = aimsInstructions();

    expect($instructions)
        ->toContain('workspace_slug')
        ->toContain('TEAMKEY-N')
        ->toContain('(team_key, number)')
        ->toContain('aims://guides/planning')
        ->toContain('aims://guides/diagrams');
});

it('stays short enough that truncating clients keep the rules', function () {
    expect(mb_strlen(aimsInstructions()))->toBeLessThan(1200);
});
