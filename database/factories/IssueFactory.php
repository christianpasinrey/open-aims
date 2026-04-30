<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    protected $model = Issue::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'team_id' => Team::factory(),
            'number' => fake()->unique()->numberBetween(1, 99999),
            'title' => fake()->sentence(),
            'description' => null,
            'workflow_state_id' => WorkflowState::factory(),
            'priority' => 0,
            'creator_user_id' => User::factory(),
            'sort_order' => 0,
        ];
    }
}
