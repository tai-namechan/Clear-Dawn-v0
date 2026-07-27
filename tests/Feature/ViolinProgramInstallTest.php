<?php

namespace Tests\Feature;

use App\Enums\DayAssignmentMode;
use App\Enums\RoutinePlanStatus;
use App\Models\Program;
use App\Models\ProgramWeekItemPrescription;
use App\Models\RoutinePlan;
use App\Models\User;
use App\Services\GenerateProgramDayPlansService;
use App\Services\InstallViolinProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ViolinProgramInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_violin_program_command_creates_the_ten_week_program(): void
    {
        $user = User::factory()->create();

        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])
            ->assertSuccessful();

        $program = Program::query()
            ->where('user_id', $user->id)
            ->where('name', InstallViolinProgramService::PROGRAM_NAME)
            ->firstOrFail();
        $version = $program->versions()->firstOrFail();

        $this->assertSame('2026-07-27', $version->starts_on->toDateString());
        $this->assertSame('2026-10-01', $version->ends_on->toDateString());
        $this->assertSame(4, $version->phases()->count());
        $this->assertSame(10, $version->weeks()->count());
        $this->assertSame(8, $version->dayTemplates()->count());
        $this->assertSame(21, $version->constraints()->count());

        // 週別処方を持つステップ種目 29 × 10週
        $this->assertSame(290, ProgramWeekItemPrescription::query()->count());
        $this->assertSame(10, ProgramWeekItemPrescription::query()->where('is_test', true)->count());
    }

    public function test_install_violin_program_command_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        $this->assertSame(1, Program::query()->where('user_id', $user->id)->count());
        $this->assertSame(290, ProgramWeekItemPrescription::query()->count());
    }

    public function test_install_violin_program_command_fails_for_unknown_user(): void
    {
        $this->artisan('cleardawn:install-violin-program', ['userId' => 999])->assertFailed();

        $this->assertSame(0, Program::query()->count());
    }

    public function test_install_violin_program_command_defaults_to_the_sole_user_when_user_id_is_omitted(): void
    {
        $user = User::factory()->create();

        $this->artisan('cleardawn:install-violin-program')->assertSuccessful();

        $this->assertSame(1, Program::query()->where('user_id', $user->id)->count());
    }

    public function test_install_violin_program_command_fails_without_user_id_when_multiple_users_exist(): void
    {
        User::factory()->count(2)->create();

        $this->artisan('cleardawn:install-violin-program')->assertFailed();

        $this->assertSame(0, Program::query()->count());
    }

    public function test_the_fallback_day_is_never_picked_by_automatic_generation(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        $version = Program::query()->where('user_id', $user->id)->firstOrFail()->versions()->firstOrFail();
        $fallback = $version->dayTemplates()->where('code', 'VIOLIN-X')->firstOrFail();

        $this->assertNull($fallback->fixed_weekday);
        $this->assertTrue($fallback->is_optional);
        $this->assertSame(DayAssignmentMode::Fallback, $fallback->assignment_mode);

        // 月〜日はすべて曜日固定DAYで埋まっているのでフォールバックは選ばれない
        foreach (range(0, 6) as $offset) {
            $date = Carbon::parse('2026-07-27')->addDays($offset);
            $plan = app(GenerateProgramDayPlansService::class)->handle($user, $date)->first();

            $this->assertNotNull($plan);
            $this->assertNotSame($fallback->id, $plan->program_day_template_id);
        }
    }

    /**
     * 疲労は自動判定できないので、フォールバックDAYは曜日DAYが無効でも自動生成しない。
     *
     * sequential にすると「版の中で1回だけ消費される」ため、最初の1日だけ生成されて
     * 以降そのプログラムのプランが二度と作られなくなる（消費されるモードにしない）。
     */
    public function test_disabling_a_weekday_day_does_not_consume_the_fallback_day(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        $version = Program::query()->where('user_id', $user->id)->firstOrFail()->versions()->firstOrFail();
        $version->dayTemplates()->where('code', 'VIOLIN-A')->update(['is_active' => false]);

        // 月曜（DAY A を無効化済み）はどの週もプランなし。フォールバックへ流れ込まない
        foreach (['2026-07-27', '2026-08-03', '2026-08-10'] as $monday) {
            $plans = app(GenerateProgramDayPlansService::class)->handle($user, Carbon::parse($monday));

            $this->assertCount(0, $plans, "{$monday} に自動生成されたプランがある");
        }

        $this->assertSame(0, RoutinePlan::query()->where('user_id', $user->id)->count());

        // 他の曜日は影響を受けない
        $tuesday = app(GenerateProgramDayPlansService::class)->handle($user, Carbon::parse('2026-07-28'))->first();
        $this->assertSame('VIOLIN-B · ミニ技術', $tuesday->title);
    }

    public function test_monday_plan_snapshots_the_week_one_prescription_into_routine_steps(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        // 2026-07-27 = 月曜 = W1 = DAY A
        $plans = app(GenerateProgramDayPlansService::class)->handle($user, Carbon::parse('2026-07-27'));

        $this->assertCount(1, $plans);
        $plan = $plans->first();

        $this->assertSame(RoutinePlanStatus::Ready, $plan->status);
        $this->assertSame('VIOLIN-A · 音・基礎＋カノン', $plan->title);
        $this->assertCount(7, $plan->steps);

        $steps = $plan->steps->sortBy('sort_order')->values();

        // 今週のCUE は初手のステップに載る
        $this->assertSame('身体＋調弦', $steps[0]->title);
        $this->assertStringContainsString('今週のCUE', (string) $steps[0]->note);
        $this->assertStringContainsString('出した後の響きまで想像する', (string) $steps[0]->note);

        // 音階は週のテンポを target_load（bpm）として持つ
        $scale = $steps->firstWhere('title', '音階（小野アンナ）');
        $this->assertNotNull($scale);
        $this->assertSame('50.00', $scale->target_load);
        $this->assertSame('bpm', $scale->load_unit);
        $this->assertSame('12.00', $scale->target_amount);
        $this->assertSame('分', $scale->amount_unit);
        $this->assertStringContainsString('G長調 1oct', (string) $scale->note);

        // 主曲はその週の到達区間を持つ
        $canon = $steps->firstWhere('title', 'カノン');
        $this->assertNotNull($canon);
        $this->assertStringContainsString('1–16小節', (string) $canon->note);

        // 曲順は資料どおり（身体→開放弦→音階→譜読み→カノン→通し／録音→記録）
        $this->assertSame(
            ['身体＋調弦', '開放弦', '音階（小野アンナ）', '譜読み', 'カノン', '通し／録音', '練習記録'],
            $steps->pluck('title')->all(),
        );
    }

    public function test_week_six_plan_uses_the_week_six_tempo_and_section(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        // 2026-08-31 = 月曜 = W6
        $plan = app(GenerateProgramDayPlansService::class)->handle($user, Carbon::parse('2026-08-31'))->first();

        $scale = $plan->steps->firstWhere('title', '音階（小野アンナ）');
        $this->assertSame('63.00', $scale->target_load);
        $this->assertStringContainsString('G長調 2oct', (string) $scale->note);

        $canon = $plan->steps->firstWhere('title', 'カノン');
        $this->assertStringContainsString('全体7割', (string) $canon->note);
    }

    public function test_sunday_plan_carries_the_weekly_test_and_progression_gate(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        // 2026-08-02 = 日曜 = W1 = DAY F
        $plan = app(GenerateProgramDayPlansService::class)->handle($user, Carbon::parse('2026-08-02'))->first();

        $this->assertSame('VIOLIN-F · 週次テスト＋好きな曲', $plan->title);

        $scaleTest = $plan->steps->firstWhere('title', '音階テスト');
        $this->assertStringContainsString('週末テスト', (string) $scaleTest->note);
        $this->assertStringContainsString('30秒動画', (string) $scaleTest->note);

        $nextWeek = $plan->steps->firstWhere('title', '翌週計画（Green／Yellow／Red）');
        $this->assertStringContainsString('進級条件', (string) $nextWeek->note);
        $this->assertStringContainsString('翌朝に痛み／しびれなし', (string) $nextWeek->note);
    }

    public function test_violin_and_training_programs_generate_plans_on_the_same_day(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        // 2026-08-01 = 土曜 = 投球DAY5 と ヴァイオリンDAY E
        $plans = app(GenerateProgramDayPlansService::class)->handle($user, Carbon::parse('2026-08-01'));

        $this->assertCount(2, $plans);
        $this->assertSame(2, RoutinePlan::query()->whereDate('scheduled_on', '2026-08-01')->count());

        $titles = $plans->pluck('title')->all();
        $this->assertContains('VIOLIN-E · 観察・非実奏', $titles);
        $this->assertContains('DAY5 · 投球日フル＋回旋パワー＋アームケア', $titles);
    }

    public function test_generation_is_idempotent_for_the_violin_program(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        $date = Carbon::parse('2026-07-28');
        app(GenerateProgramDayPlansService::class)->handle($user, $date);
        app(GenerateProgramDayPlansService::class)->handle($user, $date);

        $this->assertSame(1, RoutinePlan::query()->whereDate('scheduled_on', $date->toDateString())->count());
    }
}
