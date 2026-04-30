<?php

declare(strict_types=1);

use App\Modules\Cycles\Models\Cycle;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
});

describe('CycleListController::index', function () {
    it('lists all cycles by default (view=all)', function () {
        Cycle::factory()->create(['team_id' => $this->team->id, 'number' => 1]);
        Cycle::factory()->create(['team_id' => $this->team->id, 'number' => 2]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('cycles.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('cycles/Index')
            ->has('cycles', 2)
        );
    });

    it('filters to current cycles when view=current', function () {
        // Past
        Cycle::factory()->create([
            'team_id' => $this->team->id,
            'number' => 1,
            'starts_at' => now()->subDays(20),
            'ends_at' => now()->subDays(10),
        ]);
        // Active (current)
        Cycle::factory()->create([
            'team_id' => $this->team->id,
            'number' => 2,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(5),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('cycles.index', ['view' => 'current']));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('cycles', 1));
    });

    it('filters to upcoming cycles when view=upcoming', function () {
        Cycle::factory()->create([
            'team_id' => $this->team->id,
            'number' => 1,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(15),
        ]);
        Cycle::factory()->create([
            'team_id' => $this->team->id,
            'number' => 2,
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addDays(2),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('cycles.index', ['view' => 'upcoming']));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('cycles', 1));
    });

    it('filters to completed cycles when view=completed', function () {
        Cycle::factory()->create([
            'team_id' => $this->team->id,
            'number' => 1,
            'starts_at' => now()->subDays(20),
            'ends_at' => now()->subDays(10),
        ]);
        Cycle::factory()->create([
            'team_id' => $this->team->id,
            'number' => 2,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(5),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('cycles.index', ['view' => 'completed']));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('cycles', 1));
    });

    it('respects sort=number_desc', function () {
        Cycle::factory()->create([
            'team_id' => $this->team->id,
            'number' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        Cycle::factory()->create([
            'team_id' => $this->team->id,
            'number' => 2,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('cycles.index', ['sort' => 'number_desc']));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cycles.0.number', 2)
            ->where('cycles.1.number', 1)
        );
    });
});
