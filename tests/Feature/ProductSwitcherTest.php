<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ProductCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_yoyu(): void
    {
        $this->get(route('yoyu.home'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_to_login_from_kioku(): void
    {
        $this->get(route('kioku.home'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_yoyu_placeholder(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Yoyu/ComingSoon')
                ->where('currentProduct', ProductCatalog::YOYU)
                ->has('products', 3)
                ->where('products.1.key', ProductCatalog::YOYU)
                ->where('products.1.name', 'ヨユウ')
            );
    }

    public function test_authenticated_users_can_visit_kioku_placeholder(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('kioku.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kioku/ComingSoon')
                ->where('currentProduct', ProductCatalog::KIOKU)
                ->has('products', 3)
                ->where('products.2.key', ProductCatalog::KIOKU)
                ->where('products.2.name', 'キオク')
            );
    }

    public function test_dashboard_shares_clear_dawn_as_current_product(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('currentProduct', ProductCatalog::CLEAR_DAWN)
                ->has('products', 3)
                ->where('products.0.key', ProductCatalog::CLEAR_DAWN)
                ->where('products.0.tagline', '思考の整理・人生の方針')
                ->where('products.1.tagline', '焦らず、前へ回す秘書')
                ->where('products.2.tagline', '記憶の保存・検索・想起')
            );
    }

    public function test_product_hrefs_point_to_named_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.0.href', route('dashboard'))
                ->where('products.1.href', route('yoyu.home'))
                ->where('products.2.href', route('kioku.home'))
            );
    }
}
