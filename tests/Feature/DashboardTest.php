<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_visiting_the_dashboard_are_redirected_to_issues()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // The dashboard route is `Route::redirect('dashboard', '/issues')`
        // so an authenticated visit returns a 302 to /issues.
        $response = $this->get(route('dashboard'));
        $response->assertRedirect('/issues');
    }
}
