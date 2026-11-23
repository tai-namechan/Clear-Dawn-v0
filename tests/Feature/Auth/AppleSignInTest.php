<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\ResolveAppleUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use Mockery\MockInterface;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\OAuth2\User as SocialiteUser;
use Tests\TestCase;

class AppleSignInTest extends TestCase
{
    use RefreshDatabase;

    private const APP_USER_AGENT = 'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 ClearDawnApp';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.apple.client_id', 'jp.seedk.cleardawn.web');
    }

    private function appleUser(string $sub = 'apple-sub-1', ?string $email = 'taro@privaterelay.appleid.com', ?string $name = null): SocialiteUser
    {
        return (new SocialiteUser)->map([
            'id' => $sub,
            'email' => $email,
            'name' => $name,
        ]);
    }

    private function mockProvider(): MockInterface
    {
        $provider = Mockery::mock(AppleProvider::class);
        Socialite::shouldReceive('driver')->with('apple')->andReturn($provider);

        return $provider;
    }

    private function nativeSignIn(array $payload = []): TestResponse
    {
        $nonce = $this->getJson(route('auth.apple.nonce'))->assertOk()->json('nonce');
        $this->assertIsString($nonce);

        return $this->postJson(route('auth.apple.native'), array_merge([
            'identity_token' => 'header.payload.signature',
        ], $payload));
    }

    public function test_native_sign_in_creates_an_account_and_logs_in(): void
    {
        $provider = $this->mockProvider();
        $provider->shouldReceive('userByIdentityToken')
            ->once()
            ->with('header.payload.signature', Mockery::type('string'))
            ->andReturn($this->appleUser());

        $this->nativeSignIn(['given_name' => '太郎', 'family_name' => '山田'])
            ->assertOk()
            ->assertJsonPath('redirect', '/dashboard');

        $user = User::query()->where('apple_id', 'apple-sub-1')->firstOrFail();
        $this->assertSame('山田 太郎', $user->name);
        $this->assertSame('taro@privaterelay.appleid.com', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_apple_accounts_skip_the_email_verification_gate(): void
    {
        $provider = $this->mockProvider();
        $provider->shouldReceive('userByIdentityToken')->once()->andReturn($this->appleUser());

        $this->nativeSignIn()->assertOk();

        $user = User::query()->where('apple_id', 'apple-sub-1')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_native_sign_in_verifies_the_token_against_the_issued_nonce(): void
    {
        $provider = $this->mockProvider();
        $issued = null;
        $provider->shouldReceive('userByIdentityToken')
            ->once()
            ->withArgs(function (string $token, ?string $nonce) use (&$issued): bool {
                $issued = $nonce;

                return true;
            })
            ->andReturn($this->appleUser());

        $nonce = $this->getJson(route('auth.apple.nonce'))->json('nonce');
        $this->postJson(route('auth.apple.native'), ['identity_token' => 'header.payload.signature'])->assertOk();

        $this->assertSame($nonce, $issued);
    }

    public function test_a_nonce_cannot_be_reused(): void
    {
        $provider = $this->mockProvider();
        $provider->shouldReceive('userByIdentityToken')->once()->andReturn($this->appleUser());

        $this->nativeSignIn()->assertOk();
        auth()->logout();

        $this->postJson(route('auth.apple.native'), ['identity_token' => 'header.payload.signature'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('apple');
    }

    public function test_native_sign_in_without_a_nonce_is_rejected(): void
    {
        $this->mockProvider()->shouldNotReceive('userByIdentityToken');

        $this->postJson(route('auth.apple.native'), ['identity_token' => 'header.payload.signature'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('apple');
        $this->assertGuest();
    }

    public function test_an_invalid_identity_token_is_rejected(): void
    {
        $this->mockProvider()->shouldReceive('userByIdentityToken')
            ->andThrow(new InvalidStateException('bad signature'));

        $this->nativeSignIn()->assertUnprocessable()->assertJsonValidationErrors('apple');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_returning_apple_user_logs_into_the_same_account(): void
    {
        $user = User::factory()->create(['apple_id' => 'apple-sub-1', 'email' => 'taro@example.com']);
        $this->mockProvider()->shouldReceive('userByIdentityToken')->andReturn($this->appleUser());

        $this->nativeSignIn()->assertOk();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_existing_email_account_is_not_linked_automatically(): void
    {
        $existing = User::factory()->create(['email' => 'taro@privaterelay.appleid.com']);
        $this->mockProvider()->shouldReceive('userByIdentityToken')->andReturn($this->appleUser());

        $this->nativeSignIn()->assertUnprocessable()->assertJsonValidationErrors('apple');

        $this->assertGuest();
        $this->assertNull($existing->refresh()->apple_id);
    }

    public function test_a_new_account_without_an_email_is_refused(): void
    {
        $this->mockProvider()->shouldReceive('userByIdentityToken')->andReturn($this->appleUser(email: null));

        $this->nativeSignIn()->assertUnprocessable()->assertJsonValidationErrors('apple');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_name_falls_back_when_apple_shares_none(): void
    {
        $this->mockProvider()->shouldReceive('userByIdentityToken')->andReturn($this->appleUser());

        $this->nativeSignIn()->assertOk();

        $this->assertSame(ResolveAppleUserService::DEFAULT_NAME, User::query()->firstOrFail()->name);
    }

    public function test_web_callback_signs_in_and_reads_the_first_time_name(): void
    {
        $provider = $this->mockProvider();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('cookieNonce')->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($this->appleUser('apple-sub-web'));

        $this->post(route('auth.apple.callback'), [
            'code' => 'abc',
            'user' => json_encode(['name' => ['firstName' => 'Hanako', 'lastName' => 'Sato']]),
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('apple_id', 'apple-sub-web')->firstOrFail();
        $this->assertSame('Sato Hanako', $user->name);
        $this->assertAuthenticatedAs($user);
    }

    public function test_web_callback_returns_to_login_when_the_user_cancels(): void
    {
        $this->mockProvider()->shouldNotReceive('user');

        $this->post(route('auth.apple.callback'), ['error' => 'user_cancelled_authorize'])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_web_callback_with_an_expired_nonce_shows_an_error(): void
    {
        $provider = $this->mockProvider();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('cookieNonce')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);

        $this->post(route('auth.apple.callback'), ['code' => 'abc'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('apple');
        $this->assertGuest();
    }

    public function test_apple_routes_are_hidden_when_not_configured(): void
    {
        config()->set('services.apple.client_id', null);

        $this->get(route('auth.apple.redirect'))->assertNotFound();
        $this->getJson(route('auth.apple.nonce'))->assertNotFound();
        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('appleSignInEnabled', false));
    }

    public function test_login_and_register_screens_offer_apple_when_configured(): void
    {
        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('appleSignInEnabled', true));
        $this->get(route('register'))->assertInertia(fn ($page) => $page->where('appleSignInEnabled', true));
    }

    public function test_the_ios_app_opens_on_the_sign_in_screen_instead_of_the_landing_page(): void
    {
        $this->withHeader('User-Agent', self::APP_USER_AGENT)
            ->get(route('home'))
            ->assertRedirect(route('login'));

        $this->flushHeaders();

        $this->get(route('home'))->assertOk()->assertInertia(fn ($page) => $page->component('PublicLandingPage'));
    }

    public function test_signed_in_users_cannot_use_the_apple_endpoints(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson(route('auth.apple.nonce'))->assertRedirect();
    }
}
