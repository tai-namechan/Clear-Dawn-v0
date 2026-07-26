<?php

namespace Tests\Feature;

use App\Enums\RecommendationStatus;
use App\Models\PersonalProfileEntry;
use App\Models\Recommendation;
use App\Models\User;
use App\Services\EvaluateRulesForDayService;
use App\Services\GenerateProgramDayPlansService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Prove calibration-period copy tells users why soft alerts are suppressed.
 */
class CalibrationCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_calibration_card_explains_personal_baseline_is_still_forming(): void
    {
        $user = User::factory()->create();
        $date = Carbon::parse('2026-07-21');

        app(EvaluateRulesForDayService::class)->handle($user, $date);

        $calibration = Recommendation::query()
            ->where('user_id', $user->id)
            ->where('title', '個人の基準づくりの期間です')
            ->where('status', RecommendationStatus::Pending)
            ->firstOrFail();

        $this->assertStringContainsString(
            'あなた個人の基準が固まっていません',
            (string) $calibration->rationale,
        );
        $this->assertStringContainsString(
            '厳しい警告アラートは出さない',
            (string) $calibration->rationale,
        );
        $this->assertStringNotContainsString('較正期間中', (string) $calibration->title);
        $this->assertStringNotContainsString('（較正中）', (string) $calibration->rationale);
    }

    public function test_program_day_ready_rationale_explains_soft_alert_suppression_while_calibrating(): void
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
        app(EvaluateRulesForDayService::class)->handle($user, $date);

        $programCard = Recommendation::query()
            ->where('user_id', $user->id)
            ->where('status', RecommendationStatus::Pending)
            ->whereHas('options', fn ($q) => $q->where('action_key', 'execute'))
            ->whereHas('options', fn ($q) => $q->where('action_key', 'skip'))
            ->firstOrFail();

        $this->assertStringContainsString(
            '個人の基準がまだ固まっていないため、厳しい警告は出していません',
            (string) $programCard->rationale,
        );
        $this->assertStringNotContainsString('（較正中）', (string) $programCard->rationale);
    }
}
