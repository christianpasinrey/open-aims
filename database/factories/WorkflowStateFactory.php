<?php

namespace Database\Factories;

use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowState>
 */
class WorkflowStateFactory extends Factory
{
    protected $model = WorkflowState::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => 'Backlog',
            'type' => 'backlog',
            'color' => '#94a3b8',
            'position' => 0,
            'description' => null,
        ];
    }
}
