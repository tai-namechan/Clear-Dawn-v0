<?php

namespace Tests\Feature;

use App\Enums\PlanGenerationSource;
use App\Enums\RoutinePlanStatus;
use App\Models\PersonalProfileEntry;
use App\Models\Program;
use App\Models\ProgramChoiceOption;
use App\Models\ProgramDayTemplate;
use App\Models\ProgramPhase;
use App\Models\ProgramVersion;
use App\Models\ProgramWeek;
use App\Models\RoutinePlan;
use App\Models\User;
use App\Services\GenerateProgramDayPlansService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProgramDayPlanGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_ready_plan_for_weekday_fixed_day_with_resolved_load(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();

        PersonalProfileEntry::factory()->create([
            'user_id' => $user->id,
            'key' => PersonalProfileEntry::KEY_ONE_RM_BENCH,
            'value_numeric' => 60,
            'unit' => 'kg',
            'effective_from' => '2026-07-16',
        ]);

        // 2026-07-21 = 火曜 = DAY1（ベンチ）
        $date = Carbon::parse('2026-07-21');
        $plans = app(GenerateProgramDayPlansService::class)->handle($user, $date);

        $this->assertCount(1, $plans);
        $plan = $plans->first();
        $this->assertSame(RoutinePlanStatus::Ready, $plan->status);
        $this->assertSame(PlanGenerationSource::Program->value, $plan->generation_source);
        $this->assertNotNull($plan->program_day_template_id);
        $this->assertGreaterThan(0, $plan->steps->count());

        $benchStep = $plan->steps->first(
            fn ($step) => str_contains((string) $step->title, 'ベンチ'),
        );
        $this->assertNotNull($benchStep);
        $this->assertNotNull($benchStep->target_load);
        $this->assertNotNull($benchStep->program_step_item_id);
    }

    public function test_generation_is_idempotent_for_same_day(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();

        $date = Carbon::parse('2026-07-21');
        app(GenerateProgramDayPlansService::class)->handle($user, $date);
        app(GenerateProgramDayPlansService::class)->handle($user, $date);

        $this->assertSame(1, RoutinePlan::query()->where('user_id', $user->id)->count());
    }

    public function test_sequential_mode_generation_is_idempotent_and_does_not_advance_pointer(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->for($user)->create();
        $version = ProgramVersion::factory()->for($program)->create();
        $phase = ProgramPhase::factory()->for($version, 'version')->create();
        ProgramWeek::factory()->create([
            'program_version_id' => $version->id,
            'program_phase_id' => $phase->id,
            'starts_on' => '2026-07-20',
        ]);
        $dayA = ProgramDayTemplate::factory()->sequential()->create([
            'program_version_id' => $version->id,
            'code' => 'SEQ-A',
            'sort_order' => 1,
        ]);
        $dayB = ProgramDayTemplate::factory()->sequential()->create([
            'program_version_id' => $version->id,
            'code' => 'SEQ-B',
            'sort_order' => 2,
        ]);

        $date = Carbon::parse('2026-07-21');
        $service = app(GenerateProgramDayPlansService::class);

        $first = $service->handle($user, $date);
        $second = $service->handle($user, $date);

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame($first->first()->id, $second->first()->id);
        $this->assertSame($dayA->id, $second->first()->program_day_template_id);
        $this->assertSame(1, RoutinePlan::query()->where('user_id', $user->id)->count());

        // 翌日は次の sequential DAY に進む
        $nextPlans = $service->handle($user, Carbon::parse('2026-07-22'));
        $this->assertSame($dayB->id, $nextPlans->first()->program_day_template_id);
    }

    public function test_choice_day_starts_as_draft_until_option_selected(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();

        // 2026-07-22 = 水曜 = DAY2 選択日
        $date = Carbon::parse('2026-07-22');
        $service = app(GenerateProgramDayPlansService::class);
        $plans = $service->handle($user, $date);

        $plan = $plans->first();
        $this->assertSame(RoutinePlanStatus::Draft, $plan->status);
        $this->assertSame(0, $plan->steps->count());

        $option = ProgramChoiceOption::query()->firstOrFail();
        $filled = $service->handle($user, $date, $option->id)->first();

        $this->assertSame(RoutinePlanStatus::Ready, $filled->status);
        $this->assertSame($option->id, $filled->choice_option_id);
        $this->assertGreaterThan(0, $filled->steps->count());
    }

    public function test_choice_option_from_another_group_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();

        // 2026-07-22 = 水曜 = DAY2 選択日。別グループ（実在するが別 DAY 用）のオプションを作る
        $foreignOption = ProgramChoiceOption::factory()->create();

        $this->actingAs($user)
            ->postJson(route('today.program-choice'), [
                'date' => '2026-07-22',
                'choice_option_id' => $foreignOption->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['choice_option_id']);

        $plan = RoutinePlan::query()->where('user_id', $user->id)->first();
        if ($plan !== null) {
            $this->assertNull($plan->choice_option_id);
        }
    }

    public function test_today_index_auto_generates_program_plans(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();

        $this->actingAs($user)
            ->get(route('today.index', ['date' => '2026-07-21']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Today/Index')
                ->has('ops.program_context')
                ->has('ops.recommendations')
                ->has('plans', 1));

        $this->assertSame(1, RoutinePlan::query()->where('user_id', $user->id)->count());
        $this->assertTrue(Program::query()->where('user_id', $user->id)->exists());
    }
}
