<?php

namespace Tests\Feature;

use App\Enums\RecommendationStatus;
use App\Enums\RoutinePlanStatus;
use App\Models\DailyCheckin;
use App\Models\DailyResourceState;
use App\Models\PersonalProfileEntry;
use App\Models\Recommendation;
use App\Models\RoutinePlan;
use App\Models\SymptomObservation;
use App\Models\User;
use App\Services\GenerateProgramDayPlansService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TodayOpsPhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_upsert_checkin(): void
    {
        $this->putJson(route('today.checkin.upsert'), [
            'checked_on' => '2026-07-21',
            'fatigue' => 4,
        ])->assertUnauthorized();
    }

    public function test_user_can_upsert_daily_checkin_and_compute_resource_states(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson(route('today.checkin.upsert'), [
                'checked_on' => '2026-07-21',
                'sleep_quality' => 7,
                'fatigue' => 3,
                'muscle_soreness' => 4,
                'stress' => 2,
                'mood' => 8,
                'readiness_self' => 7,
            ])
            ->assertOk()
            ->assertJsonPath('checkin.fatigue', 3);

        $checkin = DailyCheckin::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('2026-07-21', $checkin->checked_on->toDateString());
        $this->assertSame(3, $checkin->fatigue);

        $this->assertGreaterThan(0, DailyResourceState::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, DailyCheckin::query()->where('user_id', $user->id)->count());
    }

    public function test_user_can_record_symptom_observation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('today.symptoms.store'), [
                'observed_on' => '2026-07-21',
                'body_region' => 'elbow_ulnar',
                'symptom_kind' => 'neural_ulnar',
                'severity' => 6,
                'is_new' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('symptom.symptom_kind', 'neural_ulnar');

        $this->assertSame(1, SymptomObservation::query()->where('user_id', $user->id)->count());
    }

    public function test_recommendation_decision_applies_approval_a_skip(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();

        PersonalProfileEntry::factory()->create([
            'user_id' => $user->id,
            'key' => PersonalProfileEntry::KEY_ONE_RM_BENCH,
            'value_numeric' => 60,
            'effective_from' => '2026-07-16',
        ]);

        $date = Carbon::parse('2026-07-21');
        app(GenerateProgramDayPlansService::class)->handle($user, $date);

        $this->actingAs($user)
            ->get(route('today.index', ['date' => $date->toDateString()]))
            ->assertOk();

        $programCard = Recommendation::query()
            ->where('user_id', $user->id)
            ->where('status', RecommendationStatus::Pending)
            ->whereHas('options', fn ($q) => $q->where('action_key', 'skip'))
            ->whereHas('options', fn ($q) => $q->where('action_key', 'execute'))
            ->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('recommendations.decisions.store', $programCard), [
                'action_key' => 'skip',
                'reason' => '肘の違和感のため見送り',
            ])
            ->assertCreated()
            ->assertJsonPath('decision.action_key', 'skip');

        $plan = RoutinePlan::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(RoutinePlanStatus::Archived, $plan->status);
        $this->assertSame('肘の違和感のため見送り', $plan->adjustment_reason);
        $this->assertSame(RecommendationStatus::Decided, $programCard->fresh()->status);
    }

    public function test_other_user_cannot_decide_recommendation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->artisan('cleardawn:install-program', ['userId' => $owner->id])->assertSuccessful();

        $this->actingAs($owner)
            ->get(route('today.index', ['date' => '2026-07-21']))
            ->assertOk();

        $recommendation = Recommendation::query()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($other)
            ->postJson(route('recommendations.decisions.store', $recommendation), [
                'action_key' => 'execute',
                'reason' => 'ng',
            ])
            ->assertForbidden();
    }
}
