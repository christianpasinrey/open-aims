<?php

namespace Database\Factories;

use App\Modules\Projects\Models\Project;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $name = fake()->unique()->catchPhrase();

        return [
            'workspace_id' => Workspace::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => null,
            'state' => 'backlog',
            'lead_user_id' => null,
            'start_date' => null,
            'target_date' => null,
            'color' => '#6366f1',
            'icon' => null,
            'sort_order' => 0,
            'completed_at' => null,
        ];
    }
}
