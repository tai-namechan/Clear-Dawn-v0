<?php

namespace Tests\Feature;

use App\Enums\RoutinePlanStatus;
use App\Models\RoutinePlan;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTodayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
    }

    public function test_guests_cannot_see_today_on_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_today_includes_only_todays_plans(): void
    {
        Carbon::setTestNow('2026-07-07 09:00:00');

        $user = User::factory()->create();
        RoutinePlan::factory()->ready()->create([
            'user_id' => $user->id,
            'title' => '今日のプラン',
            'scheduled_on' => '2026-07-07',
        ]);
        RoutinePlan::factory()->ready()->create([
            'user_id' => $user->id,
            'title' => '昨日のプラン',
            'scheduled_on' => '2026-07-06',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('todayRoutines.date', '2026-07-07')
                ->has('todayRoutines.plans', 1)
                ->where('todayRoutines.plans.0.title', '今日のプラン')
            );

        Carbon::setTestNow();
    }

    public function test_dashboard_today_excludes_archived_plans(): void
    {
        Carbon::setTestNow('2026-07-07 09:00:00');

        $user = User::factory()->create();
        RoutinePlan::factory()->ready()->create([
            'user_id' => $user->id,
            'title' => '実施予定',
            'scheduled_on' => '2026-07-07',
        ]);
        RoutinePlan::factory()->create([
            'user_id' => $user->id,
            'title' => 'アーカイブ済み',
            'scheduled_on' => '2026-07-07',
            'status' => RoutinePlanStatus::Archived,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('todayRoutines.plans', 1)
                ->where('todayRoutines.plans.0.title', '実施予定')
            );

        Carbon::setTestNow();
    }

    public function test_dashboard_today_excludes_other_users_plans(): void
    {
        Carbon::setTestNow('2026-07-07 09:00:00');

        $user = User::factory()->create();
        RoutinePlan::factory()->ready()->create([
            'user_id' => $user->id,
            'title' => '自分のプラン',
            'scheduled_on' => '2026-07-07',
        ]);
        RoutinePlan::factory()->ready()->create([
            'title' => '他人のプラン',
            'scheduled_on' => '2026-07-07',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('todayRoutines.plans', 1)
                ->where('todayRoutines.plans.0.title', '自分のプラン')
            );

        Carbon::setTestNow();
    }
}
