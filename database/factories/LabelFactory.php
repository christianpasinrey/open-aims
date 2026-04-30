<?php

namespace Database\Factories;

use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    protected $model = Label::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->word(),
            'color' => '#64748b',
            'description' => null,
        ];
    }
}
