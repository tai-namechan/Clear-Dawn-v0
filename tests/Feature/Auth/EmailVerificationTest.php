<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());
    }

    public function test_email_verification_screen_can_be_rendered()
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk();
    }

    public function test_email_can_be_verified()
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    /**
     * 監査 C-1 の回帰テスト。
     *
     * User が MustVerifyEmail を実装していないと EnsureEmailIsVerified が
     * 黙って素通りし、全ルートの 'verified' ミドルウェアが no-op になる。
     * 検証ルート単体のテストではこれを検知できなかったため、
     * 「保護ルートが実際にゲートされていること」を直接固定する。
     *
     * @return list<array{0: string}>
     */
    public static function protectedRouteProvider(): array
    {
        return [
            'clear dawn dashboard' => ['dashboard'],
            'yoyu home' => ['yoyu.home'],
            'kioku home' => ['kioku.home'],
        ];
    }

    #[DataProvider('protectedRouteProvider')]
    public function test_unverified_user_cannot_reach_protected_routes(string $routeName): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route($routeName))
            ->assertRedirect(route('verification.notice'));
    }

    #[DataProvider('protectedRouteProvider')]
    public function test_verified_user_can_reach_protected_routes(string $routeName): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route($routeName))
            ->assertSuccessful();
    }

    public function test_user_model_implements_must_verify_email(): void
    {
        // これが外れると verified ミドルウェアが全ルートで no-op に戻る。
        $this->assertInstanceOf(MustVerifyEmail::class, User::factory()->make());
    }

    public function test_email_is_not_verified_with_invalid_hash()
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')],
        );

        $this->actingAs($user)->get($verificationUrl);

        Event::assertNotDispatched(Verified::class);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_invalid_user_id(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => 123, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($verificationUrl);

        Event::assertNotDispatched(Verified::class);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verified_user_is_redirected_to_dashboard_from_verification_prompt(): void
    {
        $user = User::factory()->create();

        Event::fake();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        Event::assertNotDispatched(Verified::class);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_already_verified_user_visiting_verification_link_is_redirected_without_firing_event_again(): void
    {
        $user = User::factory()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($verificationUrl)
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        Event::assertNotDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
