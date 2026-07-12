<?php

namespace Tests\Feature\Connectors;

use App\Domain\Connectors\Calendar\CalendarConnectionStatus;
use App\Domain\Connectors\Calendar\CalendarProviderResolver;
use App\Domain\Kioku\Models\Connector;
use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalendarProviderTest extends TestCase
{
    use RefreshDatabase;

    private function makeConnector(User $user, array $attributes = []): Connector
    {
        return Connector::query()->withoutUserScope()->create(array_merge([
            'user_id' => $user->id,
            'source_type' => Connector::SOURCE_GOOGLE_CALENDAR,
            'status' => 'connected',
            'external_account_email' => 'me@example.com',
            'access_token' => 'plain-access-token',
            'refresh_token' => 'plain-refresh-token',
            'last_synced_at' => now(),
        ], $attributes));
    }

    private function snapshotFor(User $user)
    {
        $from = CarbonImmutable::today('UTC');

        return app(CalendarProviderResolver::class)
            ->for($user)
            ->snapshotFor($user, $from, $from->addDay(), 'UTC');
    }

    public function test_tokens_are_encrypted_at_rest_and_decrypted_on_model(): void
    {
        $user = User::factory()->create();
        $connector = $this->makeConnector($user);

        $raw = DB::table('connectors')->where('id', $connector->id)->first();
        $this->assertNotSame('plain-access-token', $raw->access_token);
        $this->assertNotSame('plain-refresh-token', $raw->refresh_token);
        $this->assertStringNotContainsString('plain-access-token', (string) $raw->access_token);

        $fresh = Connector::query()->withoutUserScope()->find($connector->id);
        $this->assertSame('plain-access-token', $fresh->access_token);
        $this->assertSame('plain-refresh-token', $fresh->refresh_token);
    }

    public function test_tokens_are_hidden_from_serialization(): void
    {
        $user = User::factory()->create();
        $connector = $this->makeConnector($user);

        $serialized = $connector->toArray();
        $this->assertArrayNotHasKey('access_token', $serialized);
        $this->assertArrayNotHasKey('refresh_token', $serialized);
        $this->assertStringNotContainsString('plain-access-token', json_encode($connector));
    }

    public function test_disconnected_user_gets_empty_snapshot_with_connect_cta(): void
    {
        $user = User::factory()->create();

        $snapshot = $this->snapshotFor($user);

        $this->assertSame(CalendarConnectionStatus::Disconnected, $snapshot->connectionStatus);
        $this->assertSame([], $snapshot->events);
        $this->assertSame('not_connected', $snapshot->warningCode);
    }

    public function test_mock_is_not_served_outside_local_testing_even_when_configured(): void
    {
        config(['calendar.driver' => 'mock']);
        $this->app['env'] = 'production';

        $user = User::factory()->create();
        $snapshot = $this->snapshotFor($user);

        $this->assertSame(CalendarConnectionStatus::Disconnected, $snapshot->connectionStatus);

        $this->app['env'] = 'testing';
    }

    public function test_mock_is_served_when_explicitly_configured_in_testing(): void
    {
        config(['calendar.driver' => 'mock']);

        $user = User::factory()->create();
        $snapshot = $this->snapshotFor($user);

        $this->assertSame(CalendarConnectionStatus::Mock, $snapshot->connectionStatus);
        $this->assertNotEmpty($snapshot->events);
    }

    public function test_connected_with_zero_events_and_synced_at_is_fresh_empty_schedule(): void
    {
        $user = User::factory()->create();
        $this->makeConnector($user, ['last_synced_at' => now()->subMinutes(2)]);

        $snapshot = $this->snapshotFor($user);

        $this->assertSame(CalendarConnectionStatus::Connected, $snapshot->connectionStatus);
        $this->assertSame([], $snapshot->events);
        $this->assertFalse($snapshot->isStale);
        $this->assertNull($snapshot->warningCode);
    }

    public function test_connected_without_any_sync_reports_sync_pending(): void
    {
        $user = User::factory()->create();
        $this->makeConnector($user, ['status' => 'syncing', 'last_synced_at' => null]);

        $snapshot = $this->snapshotFor($user);

        $this->assertSame(CalendarConnectionStatus::Syncing, $snapshot->connectionStatus);
        $this->assertSame('sync_pending', $snapshot->warningCode);
    }

    public function test_error_with_cache_serves_stale_cache_with_warning(): void
    {
        $user = User::factory()->create();
        $connector = $this->makeConnector($user, [
            'status' => 'error',
            'last_synced_at' => now()->subHours(2),
        ]);
        $this->makeTimedEvent($user, $connector, 'e-1', '会議');

        $snapshot = $this->snapshotFor($user);

        $this->assertSame(CalendarConnectionStatus::Error, $snapshot->connectionStatus);
        $this->assertCount(1, $snapshot->events);
        $this->assertTrue($snapshot->isStale);
        $this->assertSame('sync_failed', $snapshot->warningCode);
    }

    public function test_reauthorization_error_uses_distinct_warning_code(): void
    {
        $user = User::factory()->create();
        $this->makeConnector($user, [
            'status' => 'error',
            'last_error_code' => 'reauthorization_required',
            'last_synced_at' => now()->subHours(2),
        ]);

        $snapshot = $this->snapshotFor($user);

        $this->assertSame('reauthorization_required', $snapshot->warningCode);
    }

    public function test_all_day_snapshot_uses_exclusive_end_boundary(): void
    {
        $user = User::factory()->create();
        $connector = $this->makeConnector($user);
        $today = CarbonImmutable::today('UTC');
        $tomorrow = $today->addDay();
        $yesterday = $today->subDay();

        // Today only (ends_on exclusive tomorrow) — include
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'today-allday',
            'title' => '今日の終日',
            'all_day' => true,
            'starts_on' => $today->toDateString(),
            'ends_on' => $tomorrow->toDateString(),
            'synced_at' => now(),
        ]);
        // Starts tomorrow — exclude from [today, tomorrow)
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'tomorrow-allday',
            'title' => '翌日の終日',
            'all_day' => true,
            'starts_on' => $tomorrow->toDateString(),
            'ends_on' => $tomorrow->addDay()->toDateString(),
            'synced_at' => now(),
        ]);
        // Multi-day spanning yesterday..tomorrow exclusive end — include
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'multi-allday',
            'title' => '複数日',
            'all_day' => true,
            'starts_on' => $yesterday->toDateString(),
            'ends_on' => $tomorrow->toDateString(),
            'synced_at' => now(),
        ]);

        $snapshot = $this->snapshotFor($user);
        $titles = $snapshot->allDayTitles();

        $this->assertContains('今日の終日', $titles);
        $this->assertContains('複数日', $titles);
        $this->assertNotContains('翌日の終日', $titles);
    }

    public function test_snapshot_separates_timed_allday_cancelled_transparent(): void
    {
        $user = User::factory()->create();
        $connector = $this->makeConnector($user);

        $this->makeTimedEvent($user, $connector, 'timed-1', '通常予定');
        $this->makeTimedEvent($user, $connector, 'cancelled-1', 'キャンセル済み', ['status' => 'cancelled']);
        $this->makeTimedEvent($user, $connector, 'transparent-1', '空き扱い', ['transparency' => 'transparent']);
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'allday-1',
            'title' => '終日イベント',
            'all_day' => true,
            'starts_on' => CarbonImmutable::today('UTC')->toDateString(),
            'ends_on' => CarbonImmutable::today('UTC')->addDay()->toDateString(),
            'synced_at' => now(),
        ]);

        $snapshot = $this->snapshotFor($user);

        $this->assertCount(4, $snapshot->events);
        $timedIds = array_map(fn ($e) => $e->externalId, $snapshot->timedEvents());
        $this->assertContains('timed-1', $timedIds);
        $this->assertContains('transparent-1', $timedIds);
        $this->assertNotContains('cancelled-1', $timedIds);
        $this->assertNotContains('allday-1', $timedIds);
        $this->assertSame(['終日イベント'], $snapshot->allDayTitles());
    }

    public function test_other_users_events_never_leak_into_snapshot(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->makeConnector($user);
        $otherConnector = $this->makeConnector($other);
        $this->makeTimedEvent($other, $otherConnector, 'other-1', '他人の予定');

        $snapshot = $this->snapshotFor($user);

        $this->assertSame([], $snapshot->events);
    }

    private function makeTimedEvent(User $user, Connector $connector, string $externalId, string $title, array $attributes = []): YoyuCalendarEvent
    {
        $start = CarbonImmutable::today('UTC')->setTime(10, 0);

        return YoyuCalendarEvent::query()->withoutUserScope()->create(array_merge([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => $externalId,
            'title' => $title,
            'all_day' => false,
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
            'synced_at' => now(),
        ], $attributes));
    }
}
