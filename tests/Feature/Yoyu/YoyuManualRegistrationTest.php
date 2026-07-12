<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Yoyu\Models\YoyuPlace;
use App\Domain\Yoyu\Models\YoyuTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class YoyuManualRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upsert_place_travel_minutes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('yoyu.places.upsert'), [
                'name' => 'ジム',
                'travel_minutes' => 25,
            ])
            ->assertRedirect(route('yoyu.home', ['tab' => 'today']));

        $this->assertDatabaseHas('yoyu_places', [
            'user_id' => $user->id,
            'name' => 'ジム',
            'travel_minutes' => 25,
        ]);

        $this->actingAs($user)
            ->post(route('yoyu.places.upsert'), [
                'name' => ' ジム ',
                'travel_minutes' => 30,
            ])
            ->assertRedirect();

        $this->assertSame(1, YoyuPlace::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('yoyu_places', [
            'user_id' => $user->id,
            'name' => 'ジム',
            'travel_minutes' => 30,
        ]);
    }

    public function test_place_travel_is_scoped_to_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        YoyuPlace::query()->create([
            'user_id' => $other->id,
            'name' => 'ジム',
            'travel_minutes' => 40,
        ]);

        $this->actingAs($user)
            ->post(route('yoyu.places.upsert'), [
                'name' => 'ジム',
                'travel_minutes' => 20,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('yoyu_places', [
            'user_id' => $user->id,
            'name' => 'ジム',
            'travel_minutes' => 20,
        ]);
        $this->assertDatabaseHas('yoyu_places', [
            'user_id' => $other->id,
            'name' => 'ジム',
            'travel_minutes' => 40,
        ]);
    }

    public function test_today_calendar_resolves_travel_min_from_places(): void
    {
        config(['calendar.driver' => 'mock']);
        $user = User::factory()->create();

        YoyuPlace::query()->create([
            'user_id' => $user->id,
            'name' => 'ジム',
            'travel_minutes' => 20,
        ]);

        $response = $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Yoyu/Index')
                ->has('calendar')
            );

        /** @var array<int, array{place: string, travel_min: int|null}> $calendar */
        $calendar = collect($response->original->getData()['page']['props']['calendar'])->map(fn ($row) => (array) $row)->all();

        $gym = collect($calendar)->firstWhere('place', 'ジム');
        $studio = collect($calendar)->firstWhere('place', 'スタジオ');

        $this->assertNotNull($gym);
        $this->assertSame(20, $gym['travel_min']);
        $this->assertNotNull($studio);
        $this->assertNull($studio['travel_min']);
    }

    public function test_user_can_update_task_estimate_minutes(): void
    {
        $user = User::factory()->create();
        $task = YoyuTask::factory()->create([
            'user_id' => $user->id,
            'estimate_minutes' => 30,
            'status' => 'planned',
        ]);

        $this->actingAs($user)
            ->patch(route('yoyu.tasks.update', $task), [
                'estimate_minutes' => 60,
            ])
            ->assertRedirect(route('yoyu.home', ['tab' => 'tasks']));

        $this->assertDatabaseHas('yoyu_tasks', [
            'id' => $task->id,
            'estimate_minutes' => 60,
            'status' => 'planned',
        ]);
    }

    public function test_guest_cannot_upsert_place(): void
    {
        $this->post(route('yoyu.places.upsert'), [
            'name' => 'ジム',
            'travel_minutes' => 20,
        ])->assertRedirect(route('login'));
    }

    public function test_place_upsert_rejects_blank_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('yoyu.home'))
            ->post(route('yoyu.places.upsert'), [
                'name' => '   ',
                'travel_minutes' => 20,
            ])
            ->assertRedirect(route('yoyu.home'))
            ->assertSessionHasErrors('name');
    }
}
