<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia;

describe('login form rendering', function () {
    it('renders the login page for guests', function () {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('auth/Login'));
    });

    it('redirects authenticated users away from login', function () {
        $user = User::factory()->create();

        // Fortify's RedirectIfAuthenticated middleware kicks in on the login form
        // for already-authenticated users.
        $response = $this->actingAs($user)->get(route('login'));

        $response->assertStatus(302);
    });
});
