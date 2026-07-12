<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Kioku\Models\Connector;
use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use App\Domain\Yoyu\Models\YoyuPlace;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class YoyuPlacelessRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_placeless_event_can_register_place_and_fill_empty_location(): void
    {
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

        $day = CarbonImmutable::today('UTC')->startOfDay();
        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'no-place-1',
            'title' => '病院',
            'all_day' => false,
            'starts_at' => $day->setTime(15, 0),
            'ends_at' => $day->setTime(16, 0),
            'status' => 'confirmed',
            'transparency' => 'opaque',
            'location' => null,
            'synced_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('yoyu.places.upsert'), [
                'name' => '駅前クリニック',
                'travel_minutes' => 18,
                'external_id' => 'no-place-1',
            ])
            ->assertRedirect(route('yoyu.home', ['tab' => 'today']));

        $this->assertDatabaseHas('yoyu_places', [
            'user_id' => $user->id,
            'name' => '駅前クリニック',
            'travel_minutes' => 18,
        ]);
        $this->assertDatabaseHas('yoyu_calendar_events', [
            'user_id' => $user->id,
            'external_id' => 'no-place-1',
            'location' => '駅前クリニック',
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('calendar.0.travel_min', 18)
                ->where('calendar.0.place', '駅前クリニック')
            );
    }

    public function test_external_id_cannot_fill_another_users_event(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $connector = Connector::query()->withoutUserScope()->create([
            'user_id' => $other->id,
            'source_type' => Connector::SOURCE_GOOGLE_CALENDAR,
            'status' => 'connected',
            'access_token' => 'a',
            'refresh_token' => 'r',
            'last_synced_at' => now(),
        ]);

        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $other->id,
            'connector_id' => $connector->id,
            'external_id' => 'other-evt',
            'title' => '秘密',
            'all_day' => false,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'location' => null,
            'synced_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('yoyu.places.upsert'), [
                'name' => '自分の場所',
                'travel_minutes' => 10,
                'external_id' => 'other-evt',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('yoyu_places', [
            'user_id' => $user->id,
            'name' => '自分の場所',
        ]);
        $this->assertDatabaseHas('yoyu_calendar_events', [
            'user_id' => $other->id,
            'external_id' => 'other-evt',
            'location' => null,
        ]);
        $this->assertSame(0, YoyuPlace::query()->where('user_id', $other->id)->count());
    }
}
