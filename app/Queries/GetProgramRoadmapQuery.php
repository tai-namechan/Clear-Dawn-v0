<?php

namespace App\Queries;

use App\Models\PersonalProfileEntry;
use App\Models\Program;
use App\Models\ProgramWeekItemPrescription;
use App\Models\User;
use App\Support\LoadRounding;
use Illuminate\Support\Carbon;

class GetProgramRoadmapQuery
{
    /**
     * フェーズ帯 + 週タブ + DAY カード + メインリフト週次重量表を返す。
     * 表示重量 = 個人1RM（personal_profile_entries）× percent を 1.25kg 丸め（ADR-0012）。
     *
     * @return array<string, mixed>
     */
    public function handle(User $user, Program $program): array
    {
        $version = $program->activeVersion()
            ->with([
                'phases.weeks',
                'weeks.itemPrescriptions.stepItem.routineItem',
                'weeks.itemPrescriptions.stepItem.dayStep.dayTemplate',
                'dayTemplates.steps',
            ])
            ->firstOrFail();

        $oneRepMaxes = [];

        $currentWeek = $version->weekFor(Carbon::today());

        return [
            'version' => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'starts_on' => $version->starts_on->toDateString(),
                'ends_on' => $version->ends_on->toDateString(),
                'current_week_number' => $currentWeek?->week_number,
            ],
            'phases' => $version->phases->map(fn ($phase) => [
                'id' => $phase->id,
                'name' => $phase->name,
                'intent' => $phase->intent->value,
                'week_numbers' => $phase->weeks->pluck('week_number')->values()->all(),
                'progression_conditions' => $phase->progression_conditions,
            ])->values()->all(),
            'weeks' => $version->weeks->map(fn ($week) => [
                'id' => $week->id,
                'week_number' => $week->week_number,
                'starts_on' => $week->starts_on->toDateString(),
                'intent' => $week->intent,
                'prescriptions' => $week->itemPrescriptions->map(function (ProgramWeekItemPrescription $prescription) use ($user, &$oneRepMaxes) {
                    $stepItem = $prescription->stepItem;
                    $referenceLift = $stepItem->reference_lift;
                    $displayLoad = null;

                    if ($referenceLift !== null && $prescription->percent_of_reference !== null) {
                        if (! array_key_exists($referenceLift, $oneRepMaxes)) {
                            $oneRepMaxes[$referenceLift] = PersonalProfileEntry::currentFor($user, $referenceLift)?->value_numeric;
                        }

                        if ($oneRepMaxes[$referenceLift] !== null) {
                            $displayLoad = LoadRounding::r125(
                                (float) $oneRepMaxes[$referenceLift] * (float) $prescription->percent_of_reference,
                            );
                        }
                    }

                    return [
                        'id' => $prescription->id,
                        'item_name' => $stepItem->routineItem->name,
                        'day_code' => $stepItem->dayStep->dayTemplate->code,
                        'percent_of_reference' => $prescription->percent_of_reference,
                        'display_load' => $displayLoad,
                        'load_unit' => $stepItem->load_unit,
                        'sets' => $prescription->sets,
                        'reps' => $prescription->reps,
                        'rpe_target' => $prescription->rpe_target,
                        'is_test' => $prescription->is_test,
                        'intent' => $prescription->intent,
                        'note' => $prescription->note,
                    ];
                })->values()->all(),
            ])->values()->all(),
            'day_templates' => $version->dayTemplates->map(fn ($day) => [
                'id' => $day->id,
                'code' => $day->code,
                'name' => $day->name,
                'priority_tier' => $day->priority_tier->value,
                'assignment_mode' => $day->assignment_mode->value,
                'fixed_weekday' => $day->fixed_weekday,
                'estimated_minutes_min' => $day->estimated_minutes_min,
                'estimated_minutes_max' => $day->estimated_minutes_max,
                'is_optional' => $day->is_optional,
                'step_names' => $day->steps->pluck('name')->values()->all(),
            ])->values()->all(),
        ];
    }
}
