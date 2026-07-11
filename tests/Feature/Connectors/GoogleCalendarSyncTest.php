<?php

namespace Tests\Feature\Connectors;

use App\Domain\Connectors\Calendar\CalendarSyncCoordinator;
use App\Domain\Connectors\Google\GoogleCalendarApiClient;
use App\Domain\Connectors\Google\GoogleTokenManager;
use App\Domain\Connectors\Google\ReauthorizationRequiredException;
use App\Domain\Connectors\Jobs\DisconnectGoogleCalendarJob;
use App\Domain\Connectors\Jobs\SyncGoogleCalendarJob;
use App\Domain\Kioku\Models\Connector;
use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.calendar_enabled' => true,
        ]);
    }

    private function makeConnector(User $user, array $attributes = []): Connector
    {
        return Connector::query()->withoutUserScope()->create(array_merge([
            'user_id' => $user->id,
            'source_type' => Connector::SOURCE_GOOGLE_CALENDAR,
            'status' => 'connected',
            'access_token' => 'valid-access-token',
            'refresh_token' => 'valid-refresh-token',
            'token_expires_at' => now()->addHour(),
        ], $attributes));
    }

    private function googleEventsResponse(array $items, ?string $nextPageToken = null): array
    {
        return array_filter([
            'items' => $items,
            'nextPageToken' => $nextPageToken,
        ], fn ($v) => $v !== null);
    }

    private function timedItem(string $id, string $title, string $start, string $end, array $extra = []): array
    {
        return array_merge([
            'id' => $id,
            'status' => 'confirmed',
            'summary' => $title,
            'start' => ['dateTime' => $start, 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $end, 'timeZone' => 'UTC'],
        ], $extra);
    }

    public function test_token_manager_reuses_unexpired_token_without_http(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $connector = $this->makeConnector($user);

        $token = app(GoogleTokenManager::class)->validAccessToken($connector);

        $this->assertSame('valid-access-token', $token);
        Http::assertNothingSent();
    }

    public function test_token_manager_refreshes_expired_token(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'refreshed-token',
                'expires_in' => 3600,
            ]),
        ]);
        $user = User::factory()->create();
        $connector = $this->makeConnector($user, ['token_expires_at' => now()->subMinute()]);

        $token = app(GoogleTokenManager::class)->validAccessToken($connector);

        $this->assertSame('refreshed-token', $token);
        $connector->refresh();
        $this->assertSame('refreshed-token', $connector->access_token);
        // Google omitted refresh_token → keep the stored one.
        $this->assertSame('valid-refresh-token', $connector->refresh_token);
        $this->assertTrue($connector->token_expires_at->isFuture());
    }

    public function test_token_manager_invalid_grant_marks_reauthorization_required(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);
        $user = User::factory()->create();
        $connector = $this->makeConnector($user, ['token_expires_at' => now()->subMinute()]);

        $this->expectException(ReauthorizationRequiredException::class);

        try {
            app(GoogleTokenManager::class)->validAccessToken($connector);
        } finally {
            $connector->refresh();
            $this->assertSame('error', $connector->status);
            $this->assertSame('reauthorization_required', $connector->last_error_code);
        }
    }

    public function test_api_client_follows_pagination_and_normalizes_events(): void
    {
        $page1 = $this->googleEventsResponse([
            $this->timedItem('t-1', '会議', '2026-07-11T01:00:00Z', '2026-07-11T02:00:00Z'),
            [
                'id' => 'allday-1',
                'status' => 'confirmed',
                'summary' => '終日',
                'start' => ['date' => '2026-07-11'],
                'end' => ['date' => '2026-07-12'],
            ],
        ], 'page-2');
        $page2 = $this->googleEventsResponse([
            $this->timedItem('cancelled-1', 'キャンセル', '2026-07-11T03:00:00Z', '2026-07-11T04:00:00Z', ['status' => 'cancelled']),
            $this->timedItem('transparent-1', "空き\x08扱い<script>", '2026-07-11T05:00:00Z', '2026-07-11T06:00:00Z', ['transparency' => 'transparent']),
        ]);

        Http::fake(function ($request) use ($page1, $page2) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response(($query['pageToken'] ?? null) === 'page-2' ? $page2 : $page1);
        });

        $events = app(GoogleCalendarApiClient::class)->listPrimaryEvents(
            'token',
            CarbonImmutable::parse('2026-07-10T00:00:00Z'),
            CarbonImmutable::parse('2026-07-19T00:00:00Z'),
            'UTC',
        );

        $this->assertCount(4, $events);
        $byId = collect($events)->keyBy(fn ($e) => $e->externalId);
        $this->assertFalse($byId['t-1']->allDay);
        $this->assertTrue($byId['allday-1']->allDay);
        $this->assertSame('2026-07-12', $byId['allday-1']->endsOn);
        $this->assertTrue($byId['cancelled-1']->isCancelled());
        $this->assertTrue($byId['transparent-1']->isTransparent());
        // Control characters stripped; title stored as plain text.
        $this->assertStringNotContainsString("\x08", $byId['transparent-1']->title);
    }

    public function test_sync_job_upserts_cancels_and_removes_stale_rows(): void
    {
        $user = User::factory()->create();
        $connector = $this->makeConnector($user);
        $today = CarbonImmutable::now('UTC')->startOfDay();

        // Pre-existing cache: one event that will disappear from Google
        // (moved out of window) and one that becomes cancelled.
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'gone-1',
            'title' => '消えるはず',
            'all_day' => false,
            'starts_at' => $today->setTime(9, 0),
            'ends_at' => $today->setTime(10, 0),
            'synced_at' => now()->subHour(),
        ]);
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'cancel-1',
            'title' => 'キャンセルされる',
            'all_day' => false,
            'starts_at' => $today->setTime(11, 0),
            'ends_at' => $today->setTime(12, 0),
            'synced_at' => now()->subHour(),
        ]);

        $items = [
            $this->timedItem('keep-1', '残る予定', $today->setTime(13, 0)->toIso8601String(), $today->setTime(14, 0)->toIso8601String()),
            $this->timedItem('cancel-1', 'キャンセルされる', $today->setTime(11, 0)->toIso8601String(), $today->setTime(12, 0)->toIso8601String(), ['status' => 'cancelled']),
        ];
        Http::fake([
            'www.googleapis.com/*' => Http::response($this->googleEventsResponse($items)),
        ]);

        (new SyncGoogleCalendarJob($connector->id, (int) $connector->connection_version))->handle(
            app(GoogleTokenManager::class),
            app(GoogleCalendarApiClient::class),
        );
        // Idempotent re-run.
        Http::fake([
            'www.googleapis.com/*' => Http::response($this->googleEventsResponse($items)),
        ]);
        (new SyncGoogleCalendarJob($connector->id, (int) $connector->connection_version))->handle(
            app(GoogleTokenManager::class),
            app(GoogleCalendarApiClient::class),
        );

        $rows = YoyuCalendarEvent::query()->withoutUserScope()
            ->where('connector_id', $connector->id)
            ->get()
            ->keyBy('external_id');

        $this->assertCount(2, $rows);
        $this->assertSame('confirmed', $rows['keep-1']->status);
        $this->assertSame('cancelled', $rows['cancel-1']->status);
        $this->assertArrayNotHasKey('gone-1', $rows->all());

        $connector->refresh();
        $this->assertSame('connected', $connector->status);
        $this->assertNotNull($connector->last_synced_at);
        $this->assertNull($connector->last_error_code);
    }

    public function test_sync_job_zero_events_still_marks_synced(): void
    {
        $user = User::factory()->create();
        $connector = $this->makeConnector($user, ['last_synced_at' => null]);
        Http::fake([
            'www.googleapis.com/*' => Http::response($this->googleEventsResponse([])),
        ]);

        (new SyncGoogleCalendarJob($connector->id, (int) $connector->connection_version))->handle(
            app(GoogleTokenManager::class),
            app(GoogleCalendarApiClient::class),
        );

        $connector->refresh();
        $this->assertSame('connected', $connector->status);
        $this->assertNotNull($connector->last_synced_at);
    }

    public function test_sync_job_api_failure_keeps_existing_cache(): void
    {
        $user = User::factory()->create();
        $connector = $this->makeConnector($user);
        $today = CarbonImmutable::now('UTC')->startOfDay();
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'cached-1',
            'title' => 'キャッシュ済み',
            'all_day' => false,
            'starts_at' => $today->setTime(9, 0),
            'ends_at' => $today->setTime(10, 0),
            'synced_at' => now()->subHour(),
        ]);
        Http::fake([
            'www.googleapis.com/*' => Http::response(['error' => 'backend'], 503),
        ]);

        try {
            (new SyncGoogleCalendarJob($connector->id, (int) $connector->connection_version))->handle(
                app(GoogleTokenManager::class),
                app(GoogleCalendarApiClient::class),
            );
            $this->fail('Expected sync failure.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(1, YoyuCalendarEvent::query()->withoutUserScope()->count());
        $connector->refresh();
        $this->assertSame('error', $connector->status);
        $this->assertSame('sync_failed', $connector->last_error_code);
    }

    public function test_coordinator_dispatches_only_when_stale_and_not_reauth(): void
    {
        Bus::fake([SyncGoogleCalendarJob::class]);
        $user = User::factory()->create();
        $connector = $this->makeConnector($user, ['last_synced_at' => now()->subHour()]);

        app(CalendarSyncCoordinator::class)->syncIfStale($user);
        Bus::assertDispatchedTimes(SyncGoogleCalendarJob::class, 1);

        // Fresh → no dispatch.
        $connector->update(['last_synced_at' => now(), 'last_sync_attempt_at' => null]);
        app(CalendarSyncCoordinator::class)->syncIfStale($user);
        Bus::assertDispatchedTimes(SyncGoogleCalendarJob::class, 1);

        // Reauth required → user action needed, never auto-retry.
        $connector->update([
            'last_synced_at' => now()->subHour(),
            'last_error_code' => 'reauthorization_required',
            'status' => 'error',
        ]);
        app(CalendarSyncCoordinator::class)->syncIfStale($user);
        Bus::assertDispatchedTimes(SyncGoogleCalendarJob::class, 1);
    }

    public function test_stale_sync_command_dispatches_for_stale_connectors_only(): void
    {
        Bus::fake([SyncGoogleCalendarJob::class]);
        $stale = $this->makeConnector(User::factory()->create(), ['last_synced_at' => now()->subHour()]);
        $this->makeConnector(User::factory()->create(), ['last_synced_at' => now()]);
        $this->makeConnector(User::factory()->create(), [
            'last_synced_at' => now()->subHour(),
            'last_error_code' => 'reauthorization_required',
            'status' => 'error',
        ]);

        $this->artisan('calendar:sync-stale')->assertSuccessful();

        Bus::assertDispatchedTimes(SyncGoogleCalendarJob::class, 1);
        Bus::assertDispatched(SyncGoogleCalendarJob::class, fn ($job) => $job->connectorId === $stale->id
            && $job->connectionVersion === (int) $stale->connection_version);
    }

    public function test_disabled_calendar_never_dispatches_or_calls_google(): void
    {
        config(['services.google.calendar_enabled' => false]);
        Http::preventStrayRequests();
        Bus::fake([SyncGoogleCalendarJob::class]);

        $user = User::factory()->create();
        $connector = $this->makeConnector($user, ['last_synced_at' => now()->subHour()]);

        app(CalendarSyncCoordinator::class)->syncIfStale($user);
        app(CalendarSyncCoordinator::class)->forceSync($user);
        $this->artisan('calendar:sync-stale')->assertSuccessful();

        Bus::assertNotDispatched(SyncGoogleCalendarJob::class);

        (new SyncGoogleCalendarJob($connector->id, (int) $connector->connection_version))->handle(
            app(GoogleTokenManager::class),
            app(GoogleCalendarApiClient::class),
        );

        Http::assertNothingSent();
        $connector->refresh();
        $this->assertSame('connected', $connector->status);
        $this->assertTrue($connector->last_synced_at->lt(now()->subMinutes(30)));
    }

    public function test_manual_sync_endpoint_does_not_dispatch_when_disabled(): void
    {
        config(['services.google.calendar_enabled' => false]);
        Bus::fake([SyncGoogleCalendarJob::class]);
        $user = User::factory()->create();
        $this->makeConnector($user);

        $this->actingAs($user)
            ->post(route('yoyu.calendar.sync'))
            ->assertRedirect(route('yoyu.settings'));

        Bus::assertNotDispatched(SyncGoogleCalendarJob::class);
    }

    public function test_stale_generation_sync_does_not_overwrite_cache_or_status(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response($this->googleEventsResponse([
                $this->timedItem('from-a', '旧アカウント予定', '2026-07-11T01:00:00Z', '2026-07-11T02:00:00Z'),
            ])),
        ]);

        $user = User::factory()->create();
        $connector = $this->makeConnector($user, [
            'connection_version' => 2,
            'status' => 'connected',
            'last_synced_at' => now()->subMinute(),
            'external_account_email' => 'b@example.com',
        ]);
        $today = CarbonImmutable::now('UTC')->startOfDay();
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'from-b',
            'title' => '新アカウント予定',
            'all_day' => false,
            'starts_at' => $today->setTime(9, 0),
            'ends_at' => $today->setTime(10, 0),
            'synced_at' => now(),
        ]);
        $syncedAtBefore = $connector->last_synced_at->copy();

        // Generation 1 job (account A) finishing after switch to B (version 2).
        (new SyncGoogleCalendarJob($connector->id, 1))->handle(
            app(GoogleTokenManager::class),
            app(GoogleCalendarApiClient::class),
        );

        $connector->refresh();
        $this->assertSame(2, (int) $connector->connection_version);
        $this->assertSame('connected', $connector->status);
        $this->assertTrue($connector->last_synced_at->equalTo($syncedAtBefore));
        $this->assertSame(1, YoyuCalendarEvent::query()->withoutUserScope()->count());
        $this->assertSame('from-b', YoyuCalendarEvent::query()->withoutUserScope()->value('external_id'));
        Http::assertNothingSent();
    }

    public function test_new_generation_unique_id_differs_from_old_generation(): void
    {
        $old = new SyncGoogleCalendarJob('connector-1', 1);
        $new = new SyncGoogleCalendarJob('connector-1', 2);

        $this->assertNotSame($old->uniqueId(), $new->uniqueId());
        $this->assertSame('connector-1:1', $old->uniqueId());
        $this->assertSame('connector-1:2', $new->uniqueId());
    }

    public function test_disconnect_job_skips_when_generation_advanced_by_reconnect(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $connector = $this->makeConnector($user, [
            'status' => 'syncing',
            'connection_version' => 2,
            'refresh_token' => 'new-refresh',
        ]);
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'keep-me',
            'title' => '再接続後の予定',
            'all_day' => false,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'synced_at' => now(),
        ]);

        // Old disconnect for generation 1 must not delete the reconnected connector.
        (new DisconnectGoogleCalendarJob($connector->id, 1))->handle();

        $this->assertNotNull(Connector::query()->withoutUserScope()->find($connector->id));
        $this->assertSame(1, YoyuCalendarEvent::query()->withoutUserScope()->count());
        Http::assertNothingSent();
    }

    public function test_yoyu_home_serves_empty_calendar_when_disconnected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('calendar', [])
                ->where('calendarConnection.status', 'disconnected'));
    }

    public function test_yoyu_home_serves_cached_events_when_connected(): void
    {
        $user = User::factory()->create();
        $connector = $this->makeConnector($user, ['last_synced_at' => now()]);
        $today = CarbonImmutable::now((string) config('app.timezone', 'UTC'))->startOfDay();
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'today-1',
            'title' => '今日の会議',
            'all_day' => false,
            'starts_at' => $today->setTime(10, 0)->utc(),
            'ends_at' => $today->setTime(11, 0)->utc(),
            'synced_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('calendar.0.title', '今日の会議')
                ->where('calendarConnection.status', 'connected'));
    }
}
