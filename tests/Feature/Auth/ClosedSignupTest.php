<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * 監査 C-2 の回帰テスト。
 *
 * APP_PUBLIC_SIGNUP_ENABLED=false が「UI からリンクを隠す」だけで
 * POST /register が稼働し続けていた問題を固定する。
 *
 * config/fortify.php の features はアプリ起動時（LoadConfiguration）に
 * 一度だけ評価され、Fortify はそれを見てルートを登録する。したがって
 * フラグ false の挙動はテストメソッド内で config() を書き換えても再現できない。
 * アプリ生成前に環境変数を差し替える必要があるため、専用クラスに分離している。
 */
class ClosedSignupTest extends TestCase
{
    use RefreshDatabase;

    private const FLAG = 'APP_PUBLIC_SIGNUP_ENABLED';

    protected function setUp(): void
    {
        // parent::setUp() がアプリを生成する前に差し替える。
        // Dotenv のリポジトリは putenv / $_ENV / $_SERVER の3経路を見るため全て設定する。
        putenv(self::FLAG.'=false');
        $_ENV[self::FLAG] = 'false';
        $_SERVER[self::FLAG] = 'false';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // phpunit.xml の既定（true）へ戻す。他クラスの登録系テストに漏らさない。
        putenv(self::FLAG.'=true');
        $_ENV[self::FLAG] = 'true';
        $_SERVER[self::FLAG] = 'true';

        parent::tearDown();
    }

    public function test_registration_feature_is_disabled(): void
    {
        $this->assertFalse(
            Features::enabled(Features::registration()),
            'APP_PUBLIC_SIGNUP_ENABLED=false のとき registration 機能は無効であるべき。',
        );
    }

    public function test_registration_form_is_not_reachable(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_cannot_be_submitted_directly(): void
    {
        // UI を隠すだけの対策では、この直接 POST が通ってしまっていた。
        $this->post('/register', [
            'name' => 'Uninvited User',
            'email' => 'uninvited@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'uninvited@example.com']);
    }

    public function test_landing_page_does_not_offer_registration(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRegister', false));
    }

    /**
     * パスワード再設定は既存ユーザーの復旧手段であり、公開登録の可否とは無関係。
     * 以前は public_signup_enabled と AND を取っていたため、招待制運用にすると
     * 既存ユーザーが再設定リンクを見つけられなくなっていた。
     */
    public function test_password_reset_stays_available_for_existing_users(): void
    {
        $this->assertTrue(Features::enabled(Features::resetPasswords()));

        $this->get(route('home'))
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
