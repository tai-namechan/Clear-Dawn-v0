<?php

namespace App\Queries;

use App\Models\DailyCheckin;
use App\Models\NutritionGoal;
use App\Models\NutritionTargetProfile;
use App\Models\Recommendation;
use App\Models\RoutinePlan;
use App\Models\SymptomObservation;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * 今日/作戦画面向けの集約 props（plans は GetTodayQuery 側）。
 *
 * @return array<string, mixed>
 */
class GetTodayOpsQuery
{
    public function __construct(
        private readonly GetDailyMealsQuery $dailyMealsQuery,
    ) {}

    public function handle(User $user, Carbon $date): array
    {
        $checkin = DailyCheckin::query()
            ->where('user_id', $user->id)
            ->whereDate('checked_on', $date->toDateString())
            ->first();

        $recommendations = Recommendation::query()
            ->where('user_id', $user->id)
            ->whereDate('recommended_on', $date->toDateString())
            ->with(['options', 'decision'])
            ->orderByDesc('is_interrupt')
            ->orderBy('created_at')
            ->get();

        $programPlans = RoutinePlan::query()
            ->where('user_id', $user->id)
            ->whereDate('scheduled_on', $date->toDateString())
            ->whereNotNull('program_day_template_id')
            ->with([
                'dayTemplate.choiceGroup.options',
                'programWeek',
                'choiceOption',
            ])
            ->get();

        $symptoms = SymptomObservation::query()
            ->where('user_id', $user->id)
            ->where('observed_on', '>=', $date->copy()->subDays(7)->toDateString())
            ->orderByDesc('observed_on')
            ->limit(20)
            ->get();

        $nutritionProfile = NutritionTargetProfile::query()
            ->where('user_id', $user->id)
            ->whereDate('starts_on', '<=', $date->toDateString())
            ->where(function ($query) use ($date): void {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $date->toDateString());
            })
            ->orderByDesc('starts_on')
            ->first();

        $nutritionGoal = NutritionGoal::query()->where('user_id', $user->id)->first();
        $mealDay = $this->dailyMealsQuery->handle($user, $date);

        return [
            'checkin' => $checkin === null ? null : [
                'id' => $checkin->id,
                'checked_on' => $checkin->checked_on->toDateString(),
                'sleep_quality' => $checkin->sleep_quality,
                'fatigue' => $checkin->fatigue,
                'muscle_soreness' => $checkin->muscle_soreness,
                'stress' => $checkin->stress,
                'mood' => $checkin->mood,
                'region_tension' => $checkin->region_tension,
                'readiness_self' => $checkin->readiness_self,
                'note' => $checkin->note,
            ],
            'program_context' => $programPlans->map(fn (RoutinePlan $plan) => [
                'plan_id' => $plan->id,
                'title' => $plan->title,
                'status' => $plan->status->value,
                'week_number' => $plan->programWeek?->week_number,
                'day_code' => $plan->dayTemplate?->code,
                'day_name' => $plan->dayTemplate?->name,
                'choice_option_id' => $plan->choice_option_id,
                'needs_choice' => $plan->dayTemplate?->choiceGroup !== null && $plan->choice_option_id === null,
                'choice_options' => $plan->dayTemplate?->choiceGroup?->options->map(fn ($option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'description' => $option->description,
                    'estimated_minutes' => $option->estimated_minutes,
                ])->values()->all() ?? [],
            ])->values()->all(),
            'recommendations' => $recommendations->map(fn (Recommendation $recommendation) => [
                'id' => $recommendation->id,
                'title' => $recommendation->title,
                'rationale' => $recommendation->rationale,
                'goal_impact' => $recommendation->goal_impact,
                'scope' => $recommendation->scope->value,
                'confidence' => $recommendation->confidence,
                'is_interrupt' => $recommendation->is_interrupt,
                'status' => $recommendation->status->value,
                'missing_data' => $recommendation->missing_data,
                'options' => $recommendation->options->map(fn ($option) => [
                    'id' => $option->id,
                    'action_key' => $option->action_key,
                    'label' => $option->label,
                    'description' => $option->description,
                ])->values()->all(),
                'decision' => $recommendation->decision === null ? null : [
                    'action_key' => $recommendation->decision->action_key,
                    'reason' => $recommendation->decision->reason,
                ],
            ])->values()->all(),
            'recent_symptoms' => $symptoms->map(fn (SymptomObservation $symptom) => [
                'id' => $symptom->id,
                'observed_on' => $symptom->observed_on->toDateString(),
                'body_region' => $symptom->body_region,
                'symptom_kind' => $symptom->symptom_kind,
                'severity' => $symptom->severity,
                'is_new' => $symptom->is_new,
                'note' => $symptom->note,
            ])->values()->all(),
            'nutrition' => [
                'profile' => $nutritionProfile === null ? null : [
                    'name' => $nutritionProfile->name,
                    'kcal' => $nutritionProfile->kcal,
                    'protein_g' => $nutritionProfile->protein_g,
                    'fat_g' => $nutritionProfile->fat_g,
                    'carb_g' => $nutritionProfile->carb_g,
                ],
                'fallback_goal' => $nutritionGoal === null ? null : [
                    'kcal' => $nutritionGoal->kcal,
                    'protein_g' => $nutritionGoal->protein_g,
                    'fat_g' => $nutritionGoal->fat_g,
                    'carb_g' => $nutritionGoal->carb_g,
                ],
                'intake' => $mealDay['totals'],
            ],
        ];
    }
}
