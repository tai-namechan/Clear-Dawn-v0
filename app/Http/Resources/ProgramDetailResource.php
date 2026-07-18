<?php

namespace App\Http\Resources;

use App\Models\Program;
use App\Models\ProgramChoiceOption;
use App\Models\ProgramDayStep;
use App\Models\ProgramStepItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * プログラム詳細（目的・フェーズ・DAY/STEP/処方・制約・変更履歴・添付）。
 *
 * @mixin Program
 */
class ProgramDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->activeVersion;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'purpose' => $this->purpose,
            'design_philosophy' => $this->design_philosophy,
            'status' => $this->status->value,
            'goal' => $this->goal === null ? null : [
                'id' => $this->goal->id,
                'name' => $this->goal->name,
            ],
            'versions' => $this->versions->map(fn ($v) => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'status' => $v->status->value,
                'starts_on' => $v->starts_on->toDateString(),
                'ends_on' => $v->ends_on->toDateString(),
                'change_summary' => $v->change_summary,
                'change_reason' => $v->change_reason,
            ])->values()->all(),
            'active_version' => $version === null ? null : [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'starts_on' => $version->starts_on->toDateString(),
                'ends_on' => $version->ends_on->toDateString(),
                'phases' => $version->phases->map(fn ($phase) => [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'intent' => $phase->intent->value,
                    'week_numbers' => $phase->weeks->pluck('week_number')->values()->all(),
                    'progression_conditions' => $phase->progression_conditions,
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
                    'note' => $day->note,
                    'choice_group' => $day->choiceGroup === null ? null : [
                        'id' => $day->choiceGroup->id,
                        'name' => $day->choiceGroup->name,
                        'selection_hint' => $day->choiceGroup->selection_hint,
                        'options' => $day->choiceGroup->options->map(fn (ProgramChoiceOption $option) => [
                            'id' => $option->id,
                            'label' => $option->label,
                            'description' => $option->description,
                            'estimated_minutes' => $option->estimated_minutes,
                        ])->values()->all(),
                    ],
                    'steps' => $day->steps->map(fn (ProgramDayStep $step) => [
                        'id' => $step->id,
                        'name' => $step->name,
                        'step_kind' => $step->step_kind->value,
                        'required_level' => $step->required_level->value,
                        'choice_option_id' => $step->program_choice_option_id,
                        'estimated_minutes' => $step->estimated_minutes,
                        'note' => $step->note,
                        'items' => $step->items->map(fn (ProgramStepItem $item) => [
                            'id' => $item->id,
                            'name' => $item->routineItem->name,
                            'sets' => $item->sets,
                            'reps' => $item->reps,
                            'amount_value' => $item->amount_value,
                            'amount_unit' => $item->amount_unit,
                            'fixed_load' => $item->fixed_load,
                            'load_unit' => $item->load_unit,
                            'percent_of_reference' => $item->percent_of_reference,
                            'reference_lift' => $item->reference_lift,
                            'rpe_target' => $item->rpe_target,
                            'required_level' => $item->required_level->value,
                            'progression_mode' => $item->progression_mode->value,
                            'cues' => $item->cues,
                            'abort_condition' => $item->abort_condition,
                            'completion_condition' => $item->completion_condition,
                            'note' => $item->note,
                        ])->values()->all(),
                    ])->values()->all(),
                ])->values()->all(),
                'constraints' => $version->constraints->map(fn ($constraint) => [
                    'id' => $constraint->id,
                    'key' => $constraint->key,
                    'kind' => $constraint->kind,
                    'description' => $constraint->description,
                ])->values()->all(),
                'metric_targets' => $version->metricTargets->map(fn ($target) => [
                    'id' => $target->id,
                    'metric_label' => $target->metric->label,
                    'metric_unit' => $target->metric->unit,
                    'target_value' => $target->target_value,
                    'target_low' => $target->target_low,
                    'target_high' => $target->target_high,
                    'note' => $target->note,
                ])->values()->all(),
                'attachments' => $version->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'title' => $attachment->title,
                    'mime_type' => $attachment->mime_type,
                    'byte_size' => $attachment->byte_size,
                ])->values()->all(),
            ],
        ];
    }
}
