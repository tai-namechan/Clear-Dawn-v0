<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Kioku\Models\Connector;
use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use App\Domain\Yoyu\Models\YoyuPlace;
use App\Domain\Yoyu\Models\YoyuPreference;
use App\Domain\Yoyu\Services\BriefingContextBuilder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class YoyuEventTravelLeadOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_set_per_event_prep_buffer_override(): void
    {
        $user = User::factory()->create();
        $this->createTimedEvent($user, 'evt-1', 'スタジオ');

        $this->actingAs($user)
            ->patch(route('yoyu.events.travel-lead'), [
                'external_id' => 'evt-1',
                'prep_minutes' => 25,
                'buffer_minutes' => 8,
            ])
            ->assertRedirect(route('yoyu.home', ['tab' => 'today']));

        $this->assertDatabaseHas('yoyu_calendar_events', [
            'user_id' => $user->id,
            'external_id' => 'evt-1',
            'prep_minutes_override' => 25,
            'buffer_minutes_override' => 8,
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('calendar.0.prep_minutes_override', 25)
                ->where('calendar.0.buffer_minutes_override', 8)
            );
    }

    public function test_clear_restores_user_default_travel_lead(): void
    {
        $user = User::factory()->create();
        $this->createTimedEvent($user, 'evt-2', 'ジム', prepOverride: 40, bufferOverride: 15);

        YoyuPreference::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'prep_minutes' => 12,
            'buffer_minutes' => 6,
        ]);

        $this->actingAs($user)
            ->patch(route('yoyu.events.travel-lead'), [
                'external_id' => 'evt-2',
                'clear' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('yoyu_calendar_events', [
            'external_id' => 'evt-2',
            'prep_minutes_override' => null,
            'buffer_minutes_override' => null,
        ]);
    }

    public function test_cannot_override_another_users_event(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->createTimedEvent($other, 'other-evt', '秘密');

        $this->actingAs($user)
            ->patch(route('yoyu.events.travel-lead'), [
                'external_id' => 'other-evt',
                'prep_minutes' => 99,
                'buffer_minutes' => 30,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('yoyu_calendar_events', [
            'user_id' => $other->id,
            'external_id' => 'other-evt',
            'prep_minutes_override' => null,
            'buffer_minutes_override' => null,
        ]);
    }

    public function test_google_resync_does_not_wipe_travel_lead_overrides(): void
    {
        $user = User::factory()->create();
        $event = $this->createTimedEvent($user, 'evt-3', '病院', prepOverride: 20, bufferOverride: 7);

        // Simulate provider sync rewriting location only.
        YoyuCalendarEvent::query()->withoutUserScope()
            ->whereKey($event->id)
            ->update([
                'location' => '病院本院',
                'title' => '病院（更新）',
                'synced_at' => now(),
            ]);

        $this->assertDatabaseHas('yoyu_calendar_events', [
            'id' => $event->id,
            'location' => '病院本院',
            'prep_minutes_override' => 20,
            'buffer_minutes_override' => 7,
        ]);
    }

    public function test_briefing_context_applies_per_event_override_to_gaps(): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        $user = User::factory()->create();
        $connector = Connector::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'source_type' => Connector::SOURCE_GOOGLE_CALENDAR,
            'status' => 'connected',
            'external_account_email' => 'me@example.com',
            'access_token' => 'a',
            'refresh_token' => 'r',
            'last_synced_at' => now(),
        ]);

        $tz = 'Asia/Tokyo';
        $day = CarbonImmutable::parse('2026-07-11', $tz)->startOfDay();

        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'gap-evt',
            'title' => '試着',
            'all_day' => false,
            'starts_at' => $day->setTime(10, 0)->utc(),
            'ends_at' => $day->setTime(11, 0)->utc(),
            'status' => 'confirmed',
            'transparency' => 'opaque',
            'location' => '店舗',
            'prep_minutes_override' => 30,
            'buffer_minutes_override' => 10,
            'synced_at' => now(),
        ]);

        YoyuPlace::query()->create([
            'user_id' => $user->id,
            'name' => '店舗',
            'travel_minutes' => 20,
        ]);

        YoyuPreference::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'prep_minutes' => 10,
            'buffer_minutes' => 5,
        ]);

        $context = app(BriefingContextBuilder::class)->build($user, '2026-07-11', $tz);

        // Override lead 20+30+10=60 → busy 09:00-11:00 = 120 (not default 95)
        $this->assertSame(120, $context->gaps->totalBusyMinutes);
    }

    private function createTimedEvent(
        User $user,
        string $externalId,
        string $location,
        ?int $prepOverride = null,
        ?int $bufferOverride = null,
    ): YoyuCalendarEvent {
        $connector = Connector::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'source_type' => Connector::SOURCE_GOOGLE_CALENDAR,
            'status' => 'connected',
            'external_account_email' => 'me@example.com',
            'access_token' => 'a',
            'refresh_token' => 'r',
            'last_synced_at' => now(),
        ]);

        $day = CarbonImmutable::today('UTC')->startOfDay();

        return YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => $externalId,
            'title' => '予定',
            'all_day' => false,
            'starts_at' => $day->setTime(15, 0),
            'ends_at' => $day->setTime(16, 0),
            'status' => 'confirmed',
            'transparency' => 'opaque',
            'location' => $location,
            'prep_minutes_override' => $prepOverride,
            'buffer_minutes_override' => $bufferOverride,
            'synced_at' => now(),
        ]);
    }
}
