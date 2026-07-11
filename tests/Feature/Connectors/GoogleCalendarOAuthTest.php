<?php

namespace Tests\Feature\Connectors;

use App\Domain\Connectors\Jobs\DisconnectGoogleCalendarJob;
use App\Domain\Connectors\Jobs\SyncGoogleCalendarJob;
use App\Domain\Kioku\Models\Connector;
use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as OAuthUser;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GoogleCalendarOAuthTest extends TestCase
{
    use RefreshDatabase;

    private const SCOPE = 'https://www.googleapis.com/auth/calendar.readonly';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.calendar_enabled' => true,
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost/yoyu/settings/calendar/callback',
        ]);
    }

    private function fakeCallbackUser(array $overrides = []): OAuthUser
    {
        $oauthUser = (new OAuthUser)->map([
            'id' => $overrides['id'] ?? 'google-account-1',
            'email' => $overrides['email'] ?? 'me@example.com',
        ]);
        $oauthUser->token = $overrides['token'] ?? 'new-access-token';
        $oauthUser->refreshToken = array_key_exists('refreshToken', $overrides)
            ? $overrides['refreshToken']
            : 'new-refresh-token';
        $oauthUser->expiresIn = 3600;
        $oauthUser->approvedScopes = $overrides['approvedScopes'] ?? [self::SCOPE, 'openid', 'email'];

        return $oauthUser;
    }

    private function mockSocialiteCallback(OAuthUser $oauthUser): void
    {
        /** @var MockInterface&Provider $provider */
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($oauthUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_settings_page_renders_for_disconnected_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.settings'))
            ->assertOk();
    }

    public function test_guest_cannot_start_oauth(): void
    {
        $this->get(route('yoyu.calendar.connect'))->assertRedirect(route('login'));
    }

    public function test_connect_is_404_when_feature_disabled(): void
    {
        config(['services.google.calendar_enabled' => false]);

        $this->actingAs(User::factory()->create())
            ->get(route('yoyu.calendar.connect'))
            ->assertNotFound();
    }

    public function test_callback_stores_encrypted_tokens_and_dispatches_sync(): void
    {
        Bus::fake([SyncGoogleCalendarJob::class]);
        $this->mockSocialiteCallback($this->fakeCallbackUser());
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.calendar.callback', ['code' => 'x', 'state' => 'y']))
            ->assertRedirect(route('yoyu.settings'));

        $connector = Connector::query()->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('source_type', Connector::SOURCE_GOOGLE_CALENDAR)
            ->first();

        $this->assertNotNull($connector);
        $this->assertSame('syncing', $connector->status);
        $this->assertSame('me@example.com', $connector->external_account_email);
        $this->assertSame('new-access-token', $connector->access_token);
        $this->assertSame('new-refresh-token', $connector->refresh_token);

        $raw = DB::table('connectors')->where('id', $connector->id)->first();
        $this->assertStringNotContainsString('new-access-token', (string) $raw->access_token);
        $this->assertStringNotContainsString('new-refresh-token', (string) $raw->refresh_token);

        Bus::assertDispatched(SyncGoogleCalendarJob::class, fn ($job) => $job->connectorId === $connector->id
            && $job->connectionVersion === (int) $connector->connection_version);
    }

    public function test_callback_without_calendar_scope_is_rejected(): void
    {
        Bus::fake([SyncGoogleCalendarJob::class]);
        $this->mockSocialiteCallback($this->fakeCallbackUser([
            'approvedScopes' => ['openid', 'email'],
        ]));
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.calendar.callback', ['code' => 'x', 'state' => 'y']))
            ->assertRedirect(route('yoyu.settings'));

        $this->assertSame(0, Connector::query()->withoutUserScope()->count());
        Bus::assertNotDispatched(SyncGoogleCalendarJob::class);
    }

    public function test_reconnecting_same_account_keeps_existing_refresh_token_when_google_omits_it(): void
    {
        Bus::fake([SyncGoogleCalendarJob::class]);
        $user = User::factory()->create();
        $connector = Connector::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'source_type' => Connector::SOURCE_GOOGLE_CALENDAR,
            'external_account_id' => 'google-account-1',
            'refresh_token' => 'original-refresh-token',
            'status' => 'connected',
            'connection_version' => 1,
        ]);

        $this->mockSocialiteCallback($this->fakeCallbackUser(['refreshToken' => null]));

        $this->actingAs($user)
            ->get(route('yoyu.calendar.callback', ['code' => 'x', 'state' => 'y']))
            ->assertRedirect(route('yoyu.settings'));

        $connector = Connector::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertSame('original-refresh-token', $connector->refresh_token);
        $this->assertSame('syncing', $connector->status);
        $this->assertSame(2, (int) $connector->connection_version);
        Bus::assertDispatched(SyncGoogleCalendarJob::class, fn ($job) => $job->connectionVersion === 2);
    }

    public function test_switching_accounts_clears_old_cache_and_never_reuses_old_refresh_token(): void
    {
        Bus::fake([SyncGoogleCalendarJob::class]);
        $user = User::factory()->create();
        $old = Connector::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'source_type' => Connector::SOURCE_GOOGLE_CALENDAR,
            'external_account_id' => 'old-account',
            'refresh_token' => 'old-refresh-token',
            'status' => 'connected',
            'last_synced_at' => now(),
        ]);
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $old->id,
            'external_id' => 'old-event',
            'title' => '旧アカウントの予定',
            'all_day' => false,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'synced_at' => now(),
        ]);

        // New account, but Google omitted the refresh token → must NOT fall
        // back to the old account's token.
        $this->mockSocialiteCallback($this->fakeCallbackUser([
            'id' => 'new-account',
            'refreshToken' => null,
        ]));

        $this->actingAs($user)
            ->get(route('yoyu.calendar.callback', ['code' => 'x', 'state' => 'y']))
            ->assertRedirect(route('yoyu.settings'));

        $this->assertSame(0, YoyuCalendarEvent::query()->withoutUserScope()->count());
        $connector = Connector::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertNull($connector->refresh_token);
        $this->assertSame('error', $connector->status);
        $this->assertSame('reauthorization_required', $connector->last_error_code);
        Bus::assertNotDispatched(SyncGoogleCalendarJob::class);
    }

    public function test_first_connection_without_refresh_token_becomes_error(): void
    {
        Bus::fake([SyncGoogleCalendarJob::class]);
        $this->mockSocialiteCallback($this->fakeCallbackUser(['refreshToken' => null]));
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.calendar.callback', ['code' => 'x', 'state' => 'y']))
            ->assertRedirect(route('yoyu.settings'));

        $connector = Connector::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertSame('error', $connector->status);
        $this->assertSame('reauthorization_required', $connector->last_error_code);
        Bus::assertNotDispatched(SyncGoogleCalendarJob::class);
    }

    public function test_denied_grant_redirects_safely(): void
    {
        /** @var MockInterface&Provider $provider */
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('denied'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->actingAs(User::factory()->create())
            ->get(route('yoyu.calendar.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('yoyu.settings'));

        $this->assertSame(0, Connector::query()->withoutUserScope()->count());
    }

    public function test_disconnect_marks_revoking_and_dispatches_job_once(): void
    {
        Bus::fake([DisconnectGoogleCalendarJob::class]);
        $user = User::factory()->create();
        $connector = Connector::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'source_type' => Connector::SOURCE_GOOGLE_CALENDAR,
            'status' => 'connected',
        ]);

        $this->actingAs($user)
            ->delete(route('yoyu.calendar.disconnect'))
            ->assertRedirect(route('yoyu.settings'));
        $this->actingAs($user)
            ->delete(route('yoyu.calendar.disconnect'))
            ->assertRedirect(route('yoyu.settings'));

        $this->assertSame('revoking', $connector->fresh()->status);
        Bus::assertDispatchedTimes(DisconnectGoogleCalendarJob::class, 1);
        Bus::assertDispatched(
            DisconnectGoogleCalendarJob::class,
            fn ($job) => $job->connectorId === $connector->id
                && $job->connectionVersion === (int) $connector->connection_version,
        );
    }

    public function test_disconnect_job_removes_tokens_cache_and_row(): void
    {
        $user = User::factory()->create();
        $connector = Connector::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'source_type' => Connector::SOURCE_GOOGLE_CALENDAR,
            'status' => 'revoking',
            'connection_version' => 3,
            'refresh_token' => 'refresh-token',
        ]);
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'e-1',
            'title' => '予定',
            'all_day' => false,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'synced_at' => now(),
        ]);

        Http::fake([
            'oauth2.googleapis.com/revoke' => Http::response([], 200),
        ]);

        (new DisconnectGoogleCalendarJob($connector->id, 3))->handle();
        // Idempotent: second run is a no-op.
        (new DisconnectGoogleCalendarJob($connector->id, 3))->handle();

        $this->assertSame(0, Connector::query()->withoutUserScope()->count());
        $this->assertSame(0, YoyuCalendarEvent::query()->withoutUserScope()->count());
    }

    public function test_account_switch_bumps_connection_version(): void
    {
        Bus::fake([SyncGoogleCalendarJob::class]);
        $user = User::factory()->create();
        Connector::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'source_type' => Connector::SOURCE_GOOGLE_CALENDAR,
            'external_account_id' => 'old-account',
            'refresh_token' => 'old-refresh-token',
            'status' => 'connected',
            'connection_version' => 4,
            'last_synced_at' => now(),
        ]);

        $this->mockSocialiteCallback($this->fakeCallbackUser([
            'id' => 'new-account',
            'email' => 'new@example.com',
            'refreshToken' => 'brand-new-refresh',
        ]));

        $this->actingAs($user)
            ->get(route('yoyu.calendar.callback', ['code' => 'x', 'state' => 'y']))
            ->assertRedirect(route('yoyu.settings'));

        $connector = Connector::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertSame(5, (int) $connector->connection_version);
        $this->assertSame('new-account', $connector->external_account_id);
        Bus::assertDispatched(SyncGoogleCalendarJob::class, fn ($job) => $job->connectionVersion === 5);
    }
}
