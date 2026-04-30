<?php

declare(strict_types=1);

use App\Modules\Cycles\Models\Cycle;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
});

describe('CycleWriteController::store', function () {
    it('auto-numbers cycles starting at 1', function () {
        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('cycles.store', ['team' => 'ENG']), [
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addDays(7)->toDateString(),
            ])->assertRedirect();

        expect(Cycle::where('team_id', $this->team->id)->where('number', 1)->exists())->toBeTrue();
    });

    it('increments the cycle number on subsequent inserts', function () {
        Cycle::factory()->create(['team_id' => $this->team->id, 'number' => 5]);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('cycles.store', ['team' => 'ENG']), [
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addDays(7)->toDateString(),
            ]);

        expect(Cycle::where('team_id', $this->team->id)->where('number', 6)->exists())->toBeTrue();
    });

    it('returns 404 without team query string', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('cycles.store'), [
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addDays(7)->toDateString(),
            ]);

        $response->assertNotFound();
    });

    it('rejects when ends_at is before starts_at', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('cycles.index'))
            ->post(route('cycles.store', ['team' => 'ENG']), [
                'starts_at' => now()->addDays(5)->toDateString(),
                'ends_at' => now()->toDateString(),
            ]);

        $response->assertSessionHasErrors('ends_at');
    });
});

describe('CycleWriteController::update', function () {
    it('partially updates a cycle by team+number', function () {
        $cycle = Cycle::factory()->create([
            'team_id' => $this->team->id,
            'number' => 1,
            'name' => 'Old',
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('cycles.show', ['number' => 1, 'team' => 'ENG']))
            ->patch(route('cycles.update', ['number' => 1, 'team' => 'ENG']), [
                'name' => 'New',
            ])
            ->assertRedirect();

        expect($cycle->fresh()->name)->toBe('New');
    });

    it('returns 404 for an unknown cycle number', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->patch(route('cycles.update', ['number' => 999, 'team' => 'ENG']), [
                'name' => 'X',
            ]);

        $response->assertNotFound();
    });
});
