<?php

namespace Database\Factories;

use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'workspace_id' => Workspace::factory(),
            'name' => Str::title($name),
            'key' => strtoupper(Str::random(4)),
            'description' => null,
            'icon' => null,
            'color' => '#6366f1',
            'issue_counter' => 0,
            'private' => false,
        ];
    }
}
