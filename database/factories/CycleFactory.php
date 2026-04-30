<?php

namespace Database\Factories;

use App\Modules\Cycles\Models\Cycle;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cycle>
 */
class CycleFactory extends Factory
{
    protected $model = Cycle::class;

    public function definition(): array
    {
        $start = now()->subDays(3);

        return [
            'team_id' => Team::factory(),
            'name' => 'Cycle '.fake()->unique()->numberBetween(1, 999),
            'number' => fake()->unique()->numberBetween(1, 9999),
            'description' => null,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addDays(14),
            'completed_at' => null,
        ];
    }
}
