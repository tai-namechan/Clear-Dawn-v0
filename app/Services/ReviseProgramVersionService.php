<?php

namespace App\Services;

use App\Enums\ProgramVersionStatus;
use App\Models\Program;
use App\Models\ProgramVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 承認段 C: コピーオンライトで新 program_version を作る（構造の浅いコピー）。
 * DAY/STEP/処方の詳細コピーは後続で拡張可能。ここでは版メタと期間を新版として確定する。
 */
class ReviseProgramVersionService
{
    /**
     * @param  array{
     *     change_summary: string,
     *     change_reason: string,
     *     starts_on?: string|null,
     *     ends_on?: string|null
     * }  $attributes
     */
    public function handle(Program $program, array $attributes): ProgramVersion
    {
        return DB::transaction(function () use ($program, $attributes): ProgramVersion {
            /** @var ProgramVersion $active */
            $active = $program->activeVersion()->with([
                'phases.weeks',
                'dayTemplates.steps.items',
                'dayTemplates.choiceGroup.options',
                'constraints',
                'metricTargets',
                'weeks.itemPrescriptions',
            ])->firstOrFail();

            $startsOn = $attributes['starts_on'] ?? $active->starts_on->toDateString();
            $endsOn = $attributes['ends_on'] ?? $active->ends_on->toDateString();

            // starts_on 省略時のフォールバック解決後にも期間の整合を保証する
            if (Carbon::parse($endsOn)->lt(Carbon::parse($startsOn))) {
                throw ValidationException::withMessages([
                    'ends_on' => '終了日は開始日以降の日付にしてください。',
                ]);
            }

            $active->update(['status' => ProgramVersionStatus::Superseded]);

            /** @var ProgramVersion $newVersion */
            $newVersion = $program->versions()->create([
                'version_number' => $active->version_number + 1,
                'status' => ProgramVersionStatus::Active,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'change_summary' => $attributes['change_summary'],
                'change_reason' => $attributes['change_reason'],
                'approved_at' => now(),
            ]);

            $phaseMap = [];
            $weekMap = [];
            $dayMap = [];
            $stepMap = [];
            $itemMap = [];
            $optionMap = [];

            foreach ($active->phases as $phase) {
                $newPhase = $newVersion->phases()->create([
                    'name' => $phase->name,
                    'intent' => $phase->intent,
                    'sort_order' => $phase->sort_order,
                    'progression_conditions' => $phase->progression_conditions,
                ]);
                $phaseMap[$phase->id] = $newPhase->id;
            }

            foreach ($active->weeks as $week) {
                $newWeek = $newVersion->weeks()->create([
                    'program_phase_id' => $phaseMap[$week->program_phase_id],
                    'week_number' => $week->week_number,
                    'starts_on' => $week->starts_on->toDateString(),
                    'intent' => $week->intent,
                ]);
                $weekMap[$week->id] = $newWeek->id;
            }

            foreach ($active->dayTemplates as $day) {
                $newDay = $newVersion->dayTemplates()->create([
                    'code' => $day->code,
                    'name' => $day->name,
                    'priority_tier' => $day->priority_tier,
                    'assignment_mode' => $day->assignment_mode,
                    'fixed_weekday' => $day->fixed_weekday,
                    'estimated_minutes_min' => $day->estimated_minutes_min,
                    'estimated_minutes_max' => $day->estimated_minutes_max,
                    'is_optional' => $day->is_optional,
                    'is_active' => $day->is_active,
                    'sort_order' => $day->sort_order,
                    'note' => $day->note,
                ]);
                $dayMap[$day->id] = $newDay->id;

                if ($day->choiceGroup !== null) {
                    $newGroup = $newDay->choiceGroup()->create([
                        'name' => $day->choiceGroup->name,
                        'selection_hint' => $day->choiceGroup->selection_hint,
                    ]);

                    foreach ($day->choiceGroup->options as $option) {
                        $newOption = $newGroup->options()->create([
                            'label' => $option->label,
                            'description' => $option->description,
                            'estimated_minutes' => $option->estimated_minutes,
                            'sort_order' => $option->sort_order,
                        ]);
                        $optionMap[$option->id] = $newOption->id;
                    }
                }

                foreach ($day->steps as $step) {
                    $newStep = $newDay->steps()->create([
                        'program_choice_option_id' => $step->program_choice_option_id
                            ? ($optionMap[$step->program_choice_option_id] ?? null)
                            : null,
                        'name' => $step->name,
                        'step_kind' => $step->step_kind,
                        'sort_order' => $step->sort_order,
                        'required_level' => $step->required_level,
                        'purpose' => $step->purpose,
                        'estimated_minutes' => $step->estimated_minutes,
                        'start_condition' => $step->start_condition,
                        'completion_condition' => $step->completion_condition,
                        'abort_condition' => $step->abort_condition,
                        'note' => $step->note,
                    ]);
                    $stepMap[$step->id] = $newStep->id;

                    foreach ($step->items as $item) {
                        $newItem = $newStep->items()->create([
                            'routine_item_id' => $item->routine_item_id,
                            'sort_order' => $item->sort_order,
                            'sets' => $item->sets,
                            'reps' => $item->reps,
                            'amount_value' => $item->amount_value,
                            'amount_unit' => $item->amount_unit,
                            'fixed_load' => $item->fixed_load,
                            'load_unit' => $item->load_unit,
                            'percent_of_reference' => $item->percent_of_reference,
                            'reference_lift' => $item->reference_lift,
                            'rpe_target' => $item->rpe_target,
                            'rest_seconds' => $item->rest_seconds,
                            'side' => $item->side,
                            'tempo' => $item->tempo,
                            'cues' => $item->cues,
                            'required_level' => $item->required_level,
                            'progression_mode' => $item->progression_mode,
                            'alternates' => $item->alternates,
                            'abort_condition' => $item->abort_condition,
                            'completion_condition' => $item->completion_condition,
                            'note' => $item->note,
                        ]);
                        $itemMap[$item->id] = $newItem->id;
                    }
                }
            }

            foreach ($active->weeks as $week) {
                foreach ($week->itemPrescriptions as $prescription) {
                    if (! isset($itemMap[$prescription->program_step_item_id], $weekMap[$week->id])) {
                        continue;
                    }

                    $newVersion->weeks()->whereKey($weekMap[$week->id])->first()?->itemPrescriptions()->create([
                        'program_step_item_id' => $itemMap[$prescription->program_step_item_id],
                        'percent_of_reference' => $prescription->percent_of_reference,
                        'fixed_load' => $prescription->fixed_load,
                        'sets' => $prescription->sets,
                        'reps' => $prescription->reps,
                        'rpe_target' => $prescription->rpe_target,
                        'is_test' => $prescription->is_test,
                        'intent' => $prescription->intent,
                        'note' => $prescription->note,
                    ]);
                }
            }

            foreach ($active->constraints as $constraint) {
                $newVersion->constraints()->create([
                    'key' => $constraint->key,
                    'kind' => $constraint->kind,
                    'description' => $constraint->description,
                    'params' => $constraint->params,
                    'sort_order' => $constraint->sort_order,
                ]);
            }

            foreach ($active->metricTargets as $target) {
                $newVersion->metricTargets()->create([
                    'metric_id' => $target->metric_id,
                    'target_value' => $target->target_value,
                    'target_low' => $target->target_low,
                    'target_high' => $target->target_high,
                    'note' => $target->note,
                ]);
            }

            return $newVersion->fresh(['phases', 'weeks', 'dayTemplates']);
        });
    }
}
