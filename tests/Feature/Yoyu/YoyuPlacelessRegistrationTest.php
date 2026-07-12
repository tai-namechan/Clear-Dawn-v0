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

    public function test_placeless_event_can_register_override_without_touching_provider_location(): void
    {
        $user = User::factory()->create();
        $event = $this->createTimedEvent($user, 'no-place-1', location: null);

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
            'normalized_name' => '駅前クリニック',
            'travel_minutes' => 18,
        ]);
        $this->assertDatabaseHas('yoyu_calendar_events', [
            'user_id' => $user->id,
            'external_id' => 'no-place-1',
            'location' => null,
            'location_override' => '駅前クリニック',
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('calendar.0.travel_min', 18)
                ->where('calendar.0.place', '駅前クリニック')
            );

        unset($event);
    }

    public function test_google_resync_clearing_location_keeps_override(): void
    {
        $user = User::factory()->create();
        $this->createTimedEvent($user, 'evt-1', location: null);

        $this->actingAs($user)->post(route('yoyu.places.upsert'), [
            'name' => '手動クリニック',
            'travel_minutes' => 12,
            'external_id' => 'evt-1',
        ]);

        // Simulate Google sync rewriting provider location to empty without touching override.
        YoyuCalendarEvent::query()->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('external_id', 'evt-1')
            ->update(['location' => null, 'synced_at' => now()]);

        $this->assertDatabaseHas('yoyu_calendar_events', [
            'external_id' => 'evt-1',
            'location' => null,
            'location_override' => '手動クリニック',
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('calendar.0.place', '手動クリニック')
                ->where('calendar.0.travel_min', 12)
            );
    }

    public function test_google_location_wins_over_override_when_present(): void
    {
        $user = User::factory()->create();
        $this->createTimedEvent($user, 'evt-2', location: null);

        YoyuPlace::query()->create([
            'user_id' => $user->id,
            'name' => 'Google病院',
            'travel_minutes' => 40,
        ]);
        YoyuPlace::query()->create([
            'user_id' => $user->id,
            'name' => '手動クリニック',
            'travel_minutes' => 12,
        ]);

        $this->actingAs($user)->post(route('yoyu.places.upsert'), [
            'name' => '手動クリニック',
            'travel_minutes' => 12,
            'external_id' => 'evt-2',
        ]);

        YoyuCalendarEvent::query()->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('external_id', 'evt-2')
            ->update(['location' => 'Google病院', 'synced_at' => now()]);

        $this->assertDatabaseHas('yoyu_calendar_events', [
            'external_id' => 'evt-2',
            'location' => 'Google病院',
            'location_override' => '手動クリニック',
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('calendar.0.place', 'Google病院')
                ->where('calendar.0.travel_min', 40)
            );
    }

    public function test_external_id_cannot_override_another_users_event(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->createTimedEvent($other, 'other-evt', location: null);

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
            'location_override' => null,
        ]);
        $this->assertSame(0, YoyuPlace::query()->where('user_id', $other->id)->count());
    }

    public function test_unregistered_placeless_event_stays_without_travel(): void
    {
        $user = User::factory()->create();
        $this->createTimedEvent($user, 'bare', location: null);

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('calendar.0.place', '')
                ->where('calendar.0.travel_min', null)
            );
    }

    public function test_disconnect_deletes_event_including_override(): void
    {
        $user = User::factory()->create();
        $event = $this->createTimedEvent($user, 'gone', location: null);

        $this->actingAs($user)->post(route('yoyu.places.upsert'), [
            'name' => '一時場所',
            'travel_minutes' => 5,
            'external_id' => 'gone',
        ]);

        $this->assertNotNull($event->fresh()?->location_override);

        YoyuCalendarEvent::query()
            ->withoutUserScope()
            ->where('connector_id', $event->connector_id)
            ->delete();

        $this->assertDatabaseMissing('yoyu_calendar_events', [
            'external_id' => 'gone',
        ]);
    }

    private function createTimedEvent(User $user, string $externalId, ?string $location): YoyuCalendarEvent
    {
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
            'title' => '病院',
            'all_day' => false,
            'starts_at' => $day->setTime(15, 0),
            'ends_at' => $day->setTime(16, 0),
            'status' => 'confirmed',
            'transparency' => 'opaque',
            'location' => $location,
            'synced_at' => now(),
        ]);
    }
}
