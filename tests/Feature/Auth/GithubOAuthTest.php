<?php

declare(strict_types=1);

use App\Models\User;

describe('GithubOAuthController', function () {
    it('redirect aborts when oauth client id is not configured', function () {
        config()->set('services.github.client_id', null);

        $response = $this->get(route('oauth.github.redirect', ['intent' => 'login']));

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('github');
    });

    it('redirect for login intent stores intent and redirects to socialite', function () {
        config()->set('services.github.client_id', 'fake-client-id');
        config()->set('services.github.client_secret', 'fake-secret');
        config()->set('services.github.redirect', '/gh/callback');

        $response = $this->get(route('oauth.github.redirect', ['intent' => 'login']));

        $response->assertRedirect();
        // Socialite redirects to github.com/login/oauth/authorize.
        expect($response->headers->get('Location'))->toContain('github.com');
        expect(session('gh_intent'))->toBe('login');
    });

    it('redirect for connect intent without auth bounces to login', function () {
        config()->set('services.github.client_id', 'fake-client-id');
        config()->set('services.github.client_secret', 'fake-secret');

        $response = $this->get(route('oauth.github.redirect', ['intent' => 'connect']));

        $response->assertRedirect('/login');
    });

    it('callback handles GitHub-returned errors gracefully for login intent', function () {
        $response = $this->withSession(['gh_intent' => 'login'])
            ->get(route('oauth.github.callback', [
                'error' => 'access_denied',
                'error_description' => 'User canceled.',
            ]));

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('github');
    });

    it('callback handles GitHub-returned errors gracefully for connect intent', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['gh_intent' => 'connect'])
            ->get(route('oauth.github.callback', [
                'error' => 'access_denied',
            ]));

        $response->assertRedirect('/settings/profile');
        $response->assertSessionHasErrors('github');
    });

    it('disconnect requires authentication', function () {
        $response = $this->delete(route('oauth.github.disconnect'));

        $response->assertRedirect('/login');
    });

    it('disconnect clears github columns on the current user', function () {
        $user = User::factory()->create();
        $user->forceFill([
            'github_id' => '12345',
            'github_login' => 'octocat',
            'github_avatar_url' => 'https://example.com/x.png',
        ])->save();

        $response = $this->actingAs($user)->delete(route('oauth.github.disconnect'));

        $response->assertRedirect('/settings/profile');
        $user->refresh();
        expect($user->github_id)->toBeNull()
            ->and($user->github_login)->toBeNull()
            ->and($user->github_avatar_url)->toBeNull();
    });
});
