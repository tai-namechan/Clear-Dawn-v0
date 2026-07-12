<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Connectors\Calendar\CalendarEventData;
use App\Domain\Kioku\Models\Connector;
use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use App\Domain\Yoyu\Models\YoyuPlace;
use App\Domain\Yoyu\Models\YoyuPreference;
use App\Domain\Yoyu\Services\BriefingContextBuilder;
use App\Domain\Yoyu\Services\GapAnalyzer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class YoyuTravelLeadPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_shows_default_travel_lead(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Yoyu/Settings')
                ->where('travelLead.prep_minutes', 10)
                ->where('travelLead.buffer_minutes', 5)
            );
    }

    public function test_user_can_update_travel_lead_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('yoyu.settings.travel-lead'), [
                'prep_minutes' => 15,
                'buffer_minutes' => 8,
            ])
            ->assertRedirect(route('yoyu.settings'));

        $this->assertDatabaseHas('yoyu_preferences', [
            'user_id' => $user->id,
            'prep_minutes' => 15,
            'buffer_minutes' => 8,
        ]);
    }

    public function test_preferences_are_user_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        YoyuPreference::query()->withoutUserScope()->create([
            'user_id' => $other->id,
            'prep_minutes' => 30,
            'buffer_minutes' => 20,
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('travelLead.prep_minutes', 10)
                ->where('travelLead.buffer_minutes', 5)
            );
    }

    public function test_custom_prep_buffer_changes_gap_busy_minutes(): void
    {
        $analyzer = new GapAnalyzer;
        $tz = 'Asia/Tokyo';
        $date = '2026-07-11';
        $event = new CalendarEventData(
            externalId: 'e1',
            title: 'mtg',
            allDay: false,
            startsAt: CarbonImmutable::parse($date.' 10:00', $tz)->utc(),
            endsAt: CarbonImmutable::parse($date.' 11:00', $tz)->utc(),
            startsOn: null,
            endsOn: null,
            timezone: $tz,
            travelMin: 20,
        );

        $default = $analyzer->analyze($date, $tz, [$event]);
        $custom = $analyzer->analyze($date, $tz, [$event], 20, 10);

        // default lead 20+10+5=35 → busy 95; custom lead 20+20+10=50 → busy 110
        $this->assertSame(95, $default->totalBusyMinutes);
        $this->assertSame(110, $custom->totalBusyMinutes);
    }

    public function test_briefing_context_uses_user_prep_buffer(): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        $user = User::factory()->create();
        YoyuPreference::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'prep_minutes' => 20,
            'buffer_minutes' => 10,
        ]);

        $connector = Connector::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'source_type' => Connector::SOURCE_GOOGLE_CALENDAR,
            'status' => 'connected',
            'external_account_email' => 'me@example.com',
            'access_token' => 'a',
            'refresh_token' => 'r',
            'last_synced_at' => now(),
        ]);

        $day = CarbonImmutable::parse('2026-07-11', 'Asia/Tokyo')->startOfDay();
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'evt-1',
            'title' => 'ジム',
            'all_day' => false,
            'starts_at' => $day->setTime(13, 0)->utc(),
            'ends_at' => $day->setTime(14, 0)->utc(),
            'status' => 'confirmed',
            'transparency' => 'opaque',
            'location' => 'ジム',
            'synced_at' => now(),
        ]);
        YoyuPlace::query()->create([
            'user_id' => $user->id,
            'name' => 'ジム',
            'travel_minutes' => 20,
        ]);

        $context = app(BriefingContextBuilder::class)->build($user, $day, 'Asia/Tokyo');

        $this->assertSame(20, $context->travelLead['prep_minutes']);
        $this->assertSame(10, $context->travelLead['buffer_minutes']);
        $this->assertSame(110, $context->gaps->totalBusyMinutes);
    }
}
