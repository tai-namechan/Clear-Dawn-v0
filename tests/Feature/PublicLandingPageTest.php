<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicLandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_public_landing_page(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('PublicLandingPage'));
    }

    public function test_authenticated_users_are_redirected_from_home_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertRedirect(route('dashboard'));
    }
}
