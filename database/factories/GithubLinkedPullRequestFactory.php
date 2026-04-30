<?php

namespace Database\Factories;

use App\Modules\Integrations\Github\Models\GithubInstallation;
use App\Modules\Integrations\Github\Models\GithubLinkedPullRequest;
use App\Modules\Issues\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GithubLinkedPullRequest>
 */
class GithubLinkedPullRequestFactory extends Factory
{
    protected $model = GithubLinkedPullRequest::class;

    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99_999);

        return [
            'issue_id' => Issue::factory(),
            'installation_id' => GithubInstallation::factory(),
            'pr_number' => $number,
            'pr_node_id' => 'PR_'.fake()->unique()->lexify('????????'),
            'pr_title' => fake()->sentence(),
            'pr_state' => 'open',
            'pr_url' => 'https://github.com/example/repo/pull/'.$number,
            'branch_name' => 'feature/'.fake()->slug(),
            'author_login' => fake()->userName(),
            'opened_at' => now(),
            'closed_at' => null,
            'merged_at' => null,
        ];
    }
}
