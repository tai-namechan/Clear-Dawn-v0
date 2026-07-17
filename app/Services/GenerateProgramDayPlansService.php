<?php

namespace App\Services;

use App\Enums\DayAssignmentMode;
use App\Enums\PlanGenerationSource;
use App\Enums\ProgramStatus;
use App\Enums\ProgramVersionStatus;
use App\Enums\RoutinePlanStatus;
use App\Models\PersonalProfileEntry;
use App\Models\Program;
use App\Models\ProgramDayStep;
use App\Models\ProgramDayTemplate;
use App\Models\ProgramStepItem;
use App\Models\ProgramVersion;
use App\Models\ProgramWeek;
use App\Models\ProgramWeekItemPrescription;
use App\Models\RoutinePlan;
use App\Models\User;
use App\Support\LoadRounding;
use App\Support\ProgramStepKindMapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * アクティブなプログラム版から、指定日の DAY テンプレートを RoutinePlan に生成する（冪等）。
 *
 * - weekday_fixed: ISO 曜日一致の DAY
 * - sequential: 未割当の先頭 DAY（同日に未生成のもの）
 * - 選択日: choice 未選択なら Draft（ステップなし）、選択済みなら Ready
 */
class GenerateProgramDayPlansService
{
    /**
     * @return Collection<int, RoutinePlan>
     */
    public function handle(User $user, Carbon $date, ?string $choiceOptionId = null): Collection
    {
        return DB::transaction(function () use ($user, $date, $choiceOptionId): Collection {
            $created = collect();

            $programs = Program::query()
                ->where('user_id', $user->id)
                ->where('status', ProgramStatus::Active)
                ->with(['activeVersion.weeks', 'activeVersion.dayTemplates.choiceGroup.options'])
                ->get();

            foreach ($programs as $program) {
                $version = $program->activeVersion;

                if ($version === null || $version->status !== ProgramVersionStatus::Active) {
                    continue;
                }

                if (! $date->betweenIncluded($version->starts_on, $version->ends_on)) {
                    continue;
                }

                $week = $version->weekFor($date);

                if ($week === null) {
                    continue;
                }

                $dayTemplate = $this->resolveDayTemplate($version, $user, $date);

                if ($dayTemplate === null) {
                    continue;
                }

                $existing = RoutinePlan::query()
                    ->where('user_id', $user->id)
                    ->whereDate('scheduled_on', $date->toDateString())
                    ->where('program_day_template_id', $dayTemplate->id)
                    ->first();

                if ($existing !== null) {
                    if ($choiceOptionId !== null && $existing->choice_option_id === null) {
                        $created->push($this->applyChoice($user, $existing, $dayTemplate, $week, $choiceOptionId));
                    } else {
                        $created->push($existing->load('steps'));
                    }

                    continue;
                }

                $created->push($this->createPlan($user, $version, $week, $dayTemplate, $date, $choiceOptionId));
            }

            return $created->values();
        });
    }

    private function resolveDayTemplate(ProgramVersion $version, User $user, Carbon $date): ?ProgramDayTemplate
    {
        $weekday = $date->isoWeekday();

        $fixed = $version->dayTemplates
            ->filter(fn (ProgramDayTemplate $day): bool => $day->is_active
                && $day->assignment_mode === DayAssignmentMode::WeekdayFixed
                && $day->fixed_weekday === $weekday)
            ->sortBy('sort_order')
            ->first();

        if ($fixed !== null) {
            return $fixed;
        }

        $usedTemplateIds = RoutinePlan::query()
            ->where('user_id', $user->id)
            ->where('program_version_id', $version->id)
            ->whereNotNull('program_day_template_id')
            ->pluck('program_day_template_id')
            ->all();

        return $version->dayTemplates
            ->filter(fn (ProgramDayTemplate $day): bool => $day->is_active
                && $day->assignment_mode === DayAssignmentMode::Sequential
                && ! in_array($day->id, $usedTemplateIds, true))
            ->sortBy('sort_order')
            ->first();
    }

    private function createPlan(
        User $user,
        ProgramVersion $version,
        ProgramWeek $week,
        ProgramDayTemplate $dayTemplate,
        Carbon $date,
        ?string $choiceOptionId,
    ): RoutinePlan {
        $dayTemplate->loadMissing([
            'choiceGroup.options',
            'steps' => fn ($query) => $query->orderBy('sort_order'),
            'steps.items.routineItem',
            'steps.items.weekPrescriptions' => fn ($query) => $query->where('program_week_id', $week->id),
        ]);

        $needsChoice = $dayTemplate->choiceGroup !== null && $choiceOptionId === null;

        $plan = $user->routinePlans()->create([
            'title' => sprintf('%s · %s', $dayTemplate->code, $dayTemplate->name),
            'scheduled_on' => $date->toDateString(),
            'status' => $needsChoice ? RoutinePlanStatus::Draft : RoutinePlanStatus::Ready,
            'program_version_id' => $version->id,
            'program_week_id' => $week->id,
            'program_day_template_id' => $dayTemplate->id,
            'generation_source' => PlanGenerationSource::Program->value,
            'choice_option_id' => $choiceOptionId,
            'note' => $needsChoice ? '選択メニューの選択待ち' : null,
        ]);

        if (! $needsChoice) {
            $this->snapshotSteps($user, $plan, $dayTemplate, $week, $choiceOptionId);
            $plan->update(['status' => RoutinePlanStatus::Ready]);
        }

        return $plan->load('steps');
    }

    private function applyChoice(
        User $user,
        RoutinePlan $plan,
        ProgramDayTemplate $dayTemplate,
        ProgramWeek $week,
        string $choiceOptionId,
    ): RoutinePlan {
        if ($plan->sessions()->exists()) {
            return $plan->load('steps');
        }

        $dayTemplate->loadMissing([
            'steps' => fn ($query) => $query->orderBy('sort_order'),
            'steps.items.routineItem',
            'steps.items.weekPrescriptions' => fn ($query) => $query->where('program_week_id', $week->id),
        ]);

        $plan->steps()->delete();
        $this->snapshotSteps($user, $plan, $dayTemplate, $week, $choiceOptionId);

        $plan->update([
            'choice_option_id' => $choiceOptionId,
            'status' => RoutinePlanStatus::Ready,
            'note' => null,
        ]);

        return $plan->refresh()->load('steps');
    }

    private function snapshotSteps(
        User $user,
        RoutinePlan $plan,
        ProgramDayTemplate $dayTemplate,
        ProgramWeek $week,
        ?string $choiceOptionId,
    ): void {
        $sortOrder = 0;

        /** @var ProgramDayStep $dayStep */
        foreach ($dayTemplate->steps as $dayStep) {
            if ($dayStep->program_choice_option_id !== null
                && $dayStep->program_choice_option_id !== $choiceOptionId) {
                continue;
            }

            /** @var ProgramStepItem $item */
            foreach ($dayStep->items as $item) {
                $resolved = $this->resolveTargets($user, $item, $week);

                $plan->steps()->create([
                    'routine_item_id' => $item->routine_item_id,
                    'program_step_item_id' => $item->id,
                    'title' => $item->routineItem->name,
                    'purpose' => ProgramStepKindMapper::toStepPurpose($dayStep->step_kind),
                    'step_kind' => $dayStep->step_kind->value,
                    'required_level' => $item->required_level->value,
                    'sort_order' => $sortOrder++,
                    'target_load' => $resolved['target_load'],
                    'load_unit' => $resolved['load_unit'],
                    'target_amount' => $resolved['target_amount'],
                    'amount_unit' => $resolved['amount_unit'],
                    'target_blocks' => $resolved['target_blocks'],
                    'rest_seconds' => $resolved['rest_seconds'],
                    'note' => $this->composeNote($item, $resolved),
                ]);
            }
        }
    }

    /**
     * @return array{
     *     target_load: float|null,
     *     load_unit: string|null,
     *     target_amount: float|null,
     *     amount_unit: string|null,
     *     target_blocks: int|null,
     *     rest_seconds: int|null,
     *     percent_of_reference: string|null,
     *     rpe_target: string|null
     * }
     */
    private function resolveTargets(User $user, ProgramStepItem $item, ProgramWeek $week): array
    {
        /** @var ProgramWeekItemPrescription|null $prescription */
        $prescription = $item->weekPrescriptions->first(
            fn (ProgramWeekItemPrescription $row): bool => $row->program_week_id === $week->id,
        );

        $percent = $prescription?->percent_of_reference ?? $item->percent_of_reference;
        $fixedLoad = $prescription?->fixed_load ?? $item->fixed_load;
        $sets = $prescription?->sets ?? $item->sets;
        $reps = $prescription?->reps ?? $item->reps;
        $rpe = $prescription?->rpe_target ?? $item->rpe_target;

        $targetLoad = null;
        $loadUnit = $item->load_unit;

        if ($fixedLoad !== null) {
            $targetLoad = (float) $fixedLoad;
        } elseif ($percent !== null && $item->reference_lift !== null) {
            $oneRm = PersonalProfileEntry::currentFor($user, $item->reference_lift)?->value_numeric;

            if ($oneRm !== null) {
                $targetLoad = LoadRounding::r125((float) $oneRm * (float) $percent);
                $loadUnit = $loadUnit ?? 'kg';
            }
        }

        $targetAmount = $item->amount_value !== null ? (float) $item->amount_value : ($reps !== null ? (float) $reps : null);
        $amountUnit = $item->amount_unit ?? ($reps !== null ? 'reps' : null);

        return [
            'target_load' => $targetLoad,
            'load_unit' => $loadUnit,
            'target_amount' => $targetAmount,
            'amount_unit' => $amountUnit,
            'target_blocks' => $sets,
            'rest_seconds' => $item->rest_seconds,
            'percent_of_reference' => $percent,
            'rpe_target' => $rpe,
        ];
    }

    /**
     * @param  array<string, mixed>  $resolved
     */
    private function composeNote(ProgramStepItem $item, array $resolved): ?string
    {
        $parts = array_filter([
            $item->cues,
            $item->tempo !== null ? 'tempo '.$item->tempo : null,
            $item->side !== null ? 'side '.$item->side : null,
            $resolved['percent_of_reference'] !== null && $resolved['target_load'] === null
                ? sprintf('%s%% 1RM', rtrim(rtrim(number_format((float) $resolved['percent_of_reference'] * 100, 2, '.', ''), '0'), '.'))
                : null,
            $resolved['rpe_target'] !== null ? 'RPE '.$resolved['rpe_target'] : null,
            $item->note,
        ]);

        return $parts === [] ? null : implode(' / ', $parts);
    }
}
