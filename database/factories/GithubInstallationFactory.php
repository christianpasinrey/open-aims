<?php

namespace Database\Factories;

use App\Modules\Integrations\Github\Models\GithubInstallation;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GithubInstallation>
 */
class GithubInstallationFactory extends Factory
{
    protected $model = GithubInstallation::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'installation_id' => (string) fake()->unique()->numberBetween(10_000_000, 99_999_999),
            'account_login' => fake()->unique()->userName(),
            'account_type' => 'Organization',
            'repository_selection' => 'all',
            'suspended_at' => null,
        ];
    }
}
