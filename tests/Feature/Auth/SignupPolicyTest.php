<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * 現在の登録ポリシーを固定する（docs/product/signup-policy.md）。
 *
 * Clear Dawn は当面 限定利用のため、`APP_PUBLIC_SIGNUP_ENABLED=false` で
 * **UI から登録導線を出さない**。ただし `/register` を URL 直打ちされた場合の
 * 登録自体は現時点では許容しており、ルートは塞いでいない。
 *
 * 「導線を出さない」と「機能を塞ぐ」は別物であり、本テストは前者だけを
 * 保証する。後者が必要になったら、ルートを消すのではなくミドルウェアで
 * 拒否したうえで本テストを更新すること
 * （ルートを消すと Wayfinder の生成物が env で変わりフロントのビルドが壊れる）。
 *
 * なお未検証ユーザーは `verified` ミドルウェアで全ルートから弾かれるため
 * （EmailVerificationTest 参照）、直接登録できてもメール確認を通すまで
 * アプリの中身には到達できない。
 */
class SignupPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * フラグは request 時に読まれるため config の差し替えで再現できる。
     * config/fortify.php の features は起動時に一度だけ評価されるので、
     * 登録機能そのものはフラグと無関係に常時有効である。
     */
    private function setPublicSignup(bool $enabled): void
    {
        config()->set('app.public_signup_enabled', $enabled);
    }

    public function test_registration_feature_stays_enabled_regardless_of_the_flag(): void
    {
        $this->setPublicSignup(false);

        // ここが false になると Wayfinder が routes/register を生成しなくなり、
        // AuthRegisterForm.vue / AuthLoginForm.vue の静的 import が解決できず
        // フロントのビルドが失敗する。
        $this->assertTrue(Features::enabled(Features::registration()));
    }

    public function test_landing_page_hides_the_registration_entry_point_when_disabled(): void
    {
        $this->setPublicSignup(false);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRegister', false));
    }

    public function test_landing_page_offers_registration_when_enabled(): void
    {
        $this->setPublicSignup(true);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRegister', true));
    }

    public function test_login_page_hides_the_registration_entry_point_when_disabled(): void
    {
        $this->setPublicSignup(false);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRegister', false));
    }

    /**
     * 現時点で受容しているリスク。導線が無いだけでルートは生きている。
     * 塞ぐ判断をしたらこのテストを「拒否されること」に書き換える。
     */
    public function test_direct_url_registration_is_intentionally_still_reachable(): void
    {
        $this->setPublicSignup(false);

        $this->get('/register')->assertSuccessful();
    }

    /**
     * パスワード再設定は既存ユーザーの復旧手段であり、登録導線の有無とは無関係。
     * 以前は public_signup_enabled と AND を取っていたため、フラグを false に
     * すると既存ユーザーが再設定リンクを見つけられなかった（監査 C-2b）。
     */
    public function test_password_reset_stays_available_when_the_entry_point_is_hidden(): void
    {
        $this->setPublicSignup(false);

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
        $this->setPublicSignup(false);

        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }
}
