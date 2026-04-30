<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'user_id' => User::factory(),
            'parent_comment_id' => null,
            'body' => fake()->paragraph(),
            'edited_at' => null,
        ];
    }
}
