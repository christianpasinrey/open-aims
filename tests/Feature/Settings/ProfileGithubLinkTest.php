<?php

declare(strict_types=1);

use App\Models\User;

describe('profile settings — github link affordances', function () {
    it('renders the profile settings page for authenticated users', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
    });

    it('persists github_id when user has linked their account', function () {
        $user = User::factory()->create();
        $user->forceFill([
            'github_id' => '7777',
            'github_login' => 'gh-test',
        ])->save();

        $user->refresh();

        expect($user->github_id)->toBe('7777')
            ->and($user->github_login)->toBe('gh-test');
    });
});
