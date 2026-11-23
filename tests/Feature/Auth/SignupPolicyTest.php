<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * 現在の登録ポリシーを固定する（docs/product/signup-policy.md）。
 *
 * 新規登録は公開しており、導線はランディング・ログイン画面の両方に出す。
 * 公開登録の安全はメール確認（EmailVerificationTest）が担う。
 */
class SignupPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_feature_is_enabled(): void
    {
        // ここが false になると Wayfinder が routes/register を生成しなくなり、
        // 登録フォームの静的 import が解決できずフロントのビルドが失敗する。
        $this->assertTrue(Features::enabled(Features::registration()));
    }

    public function test_landing_page_offers_registration(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRegister', true));
    }

    public function test_login_page_offers_registration(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRegister', true));
    }

    /**
     * 新規登録したユーザーはメール確認が済むまでアプリの中身に入れない。
     */
    public function test_newly_registered_user_must_verify_email_before_using_the_app(): void
    {
        $this->post(route('register.store'), [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'Str0ng-Passw0rd!x',
            'password_confirmation' => 'Str0ng-Passw0rd!x',
        ]);

        $this->assertAuthenticated();
        $this->assertNull(User::query()->where('email', 'new-user@example.com')->value('email_verified_at'));

        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    /**
     * パスワード再設定は既存ユーザーの復旧手段であり、常に提供する（監査 C-2b）。
     */
    public function test_password_reset_is_always_available(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canResetPassword', true));

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canResetPassword', true));

        $this->get(route('password.request'))->assertOk();
    }

    public function test_existing_users_can_still_log_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }
}
