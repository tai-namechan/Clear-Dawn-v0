<?php

namespace App\Services;

use App\Enums\PlanGenerationSource;
use App\Enums\RecommendationStatus;
use App\Models\Recommendation;
use App\Models\RecommendationDecision;
use App\Models\RecommendationOption;
use App\Models\RoutinePlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DecideRecommendationService
{
    public function __construct(
        private readonly ApplyTodayPlanAdjustmentService $applyTodayPlanAdjustment,
    ) {}

    /**
     * @param  array{
     *     action_key: string,
     *     reason?: string|null,
     *     recommendation_option_id?: string|null
     * }  $attributes
     */
    public function handle(User $user, Recommendation $recommendation, array $attributes): RecommendationDecision
    {
        if ($recommendation->user_id !== $user->id) {
            throw new InvalidArgumentException('他ユーザーの推奨には決定できません。');
        }

        if ($recommendation->status === RecommendationStatus::Decided) {
            throw new InvalidArgumentException('すでに決定済みの推奨です。');
        }

        return DB::transaction(function () use ($user, $recommendation, $attributes): RecommendationDecision {
            $actionKey = $attributes['action_key'];
            $reason = $attributes['reason'] ?? null;

            $option = null;

            if (! empty($attributes['recommendation_option_id'])) {
                /** @var RecommendationOption $option */
                $option = $recommendation->options()->whereKey($attributes['recommendation_option_id'])->firstOrFail();
                $actionKey = $option->action_key;
            }

            $result = ['action' => $actionKey];

            if (in_array($actionKey, ['execute', 'adjust', 'lighten', 'skip'], true)
                && $recommendation->scope->value === 'A') {
                $planIds = $recommendation->plan_diff['plan_ids'] ?? [];

                foreach ($planIds as $planId) {
                    $plan = RoutinePlan::query()
                        ->where('user_id', $user->id)
                        ->whereKey($planId)
                        ->where('generation_source', PlanGenerationSource::Program->value)
                        ->first();

                    if ($plan === null) {
                        continue;
                    }

                    $adjusted = $this->applyTodayPlanAdjustment->handle($plan, [
                        'action' => $actionKey,
                        'reason' => $reason ?? ('recommendation:'.$recommendation->id),
                    ]);

                    $result['plans'][] = [
                        'id' => $adjusted->id,
                        'status' => $adjusted->status->value,
                    ];
                }
            }

            $decision = RecommendationDecision::query()->create([
                'user_id' => $user->id,
                'recommendation_id' => $recommendation->id,
                'recommendation_option_id' => $option?->id,
                'action_key' => $actionKey,
                'reason' => $reason,
                'result_snapshot' => $result,
            ]);

            $recommendation->update(['status' => RecommendationStatus::Decided]);

            return $decision;
        });
    }
}
