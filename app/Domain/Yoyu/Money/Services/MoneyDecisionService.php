<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyDecisionStatus;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Models\MoneyCreditCard;
use App\Domain\Yoyu\Money\Models\MoneyDecision;
use App\Domain\Yoyu\Money\Models\MoneyDecisionLink;
use App\Domain\Yoyu\Money\Models\MoneyLoan;
use App\Domain\Yoyu\Money\Models\MoneyRecurringRule;
use App\Domain\Yoyu\Money\Models\MoneyTransaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MoneyDecisionService
{
    public function __construct(
        private readonly MoneyAuditService $auditService,
    ) {}

    /**
     * @return Collection<int, MoneyDecision>
     */
    public function list(User $user): Collection
    {
        return MoneyDecision::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderByDesc('decided_on')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @param  array{
     *     title: string,
     *     decided_on?: string|null,
     *     status?: string|MoneyDecisionStatus,
     *     simulation_id?: string|null,
     *     before_payload?: array<string, mixed>|null,
     *     expected_effect_payload?: array<string, mixed>|null,
     *     memo?: string|null
     * }  $data
     */
    public function createManual(User $user, array $data): MoneyDecision
    {
        $status = isset($data['status'])
            ? ($data['status'] instanceof MoneyDecisionStatus
                ? $data['status']
                : MoneyDecisionStatus::from((string) $data['status']))
            : MoneyDecisionStatus::Planned;

        /** @var MoneyDecision $decision */
        $decision = MoneyDecision::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'title' => (string) $data['title'],
            'decided_on' => $data['decided_on'] ?? Date::now()->toDateString(),
            'status' => $status,
            'simulation_id' => $data['simulation_id'] ?? null,
            'before_payload' => $data['before_payload'] ?? null,
            'expected_effect_payload' => $data['expected_effect_payload'] ?? null,
            'memo' => $data['memo'] ?? null,
        ]);

        $this->auditService->record(
            (int) $user->id,
            'money_decision.created',
            MoneyDecision::class,
            (string) $decision->id,
            null,
            [
                'id' => $decision->id,
                'name' => $decision->title,
                'status' => $decision->status->value,
            ],
        );

        return $decision;
    }

    /**
     * @param  array<string, mixed>  $actualEffectPayload
     */
    public function review(
        User $user,
        MoneyDecision $decision,
        array $actualEffectPayload,
        ?string $reflection = null,
    ): MoneyDecision {
        $this->assertOwned($user, $decision);

        return DB::transaction(function () use ($user, $decision, $actualEffectPayload, $reflection): MoneyDecision {
            $before = [
                'id' => $decision->id,
                'status' => $decision->status->value,
            ];

            $payload = $actualEffectPayload;
            if ($reflection !== null && $reflection !== '') {
                $payload['reflection'] = $reflection;
                if ($decision->memo === null || $decision->memo === '') {
                    $decision->memo = $reflection;
                } else {
                    $decision->memo = rtrim($decision->memo)."\n".$reflection;
                }
            }

            $decision->actual_effect_payload = $payload;
            $decision->status = MoneyDecisionStatus::Reviewed;
            $decision->reviewed_at = Date::now();
            $decision->save();

            $this->auditService->record(
                (int) $user->id,
                'money_decision.reviewed',
                MoneyDecision::class,
                (string) $decision->id,
                $before,
                [
                    'id' => $decision->id,
                    'status' => $decision->status->value,
                ],
            );

            return $decision->refresh();
        });
    }

    public function linkEntity(
        User $user,
        MoneyDecision $decision,
        string $subjectType,
        string $subjectId,
        string $relationType = 'related',
    ): MoneyDecisionLink {
        $this->assertOwned($user, $decision);
        $this->assertSubjectOwned($user, $subjectType, $subjectId);

        /** @var MoneyDecisionLink $link */
        $link = MoneyDecisionLink::query()->withoutUserScope()->firstOrCreate(
            [
                'decision_id' => $decision->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
            ],
            [
                'user_id' => $user->id,
                'relation_type' => $relationType,
            ],
        );

        return $link;
    }

    private function assertSubjectOwned(User $user, string $subjectType, string $subjectId): void
    {
        $owned = match ($subjectType) {
            MoneyCashflow::class, 'money_cashflow' => MoneyCashflow::query()
                ->withoutUserScope()
                ->whereKey($subjectId)
                ->where('user_id', $user->id)
                ->exists(),
            MoneyTransaction::class, 'money_transaction' => MoneyTransaction::query()
                ->withoutUserScope()
                ->whereKey($subjectId)
                ->where('user_id', $user->id)
                ->exists(),
            MoneyCreditCard::class, 'money_credit_card' => MoneyCreditCard::query()
                ->withoutUserScope()
                ->whereKey($subjectId)
                ->where('user_id', $user->id)
                ->exists(),
            MoneyLoan::class, 'money_loan' => MoneyLoan::query()
                ->withoutUserScope()
                ->whereKey($subjectId)
                ->where('user_id', $user->id)
                ->exists(),
            MoneyRecurringRule::class, 'money_recurring_rule' => MoneyRecurringRule::query()
                ->withoutUserScope()
                ->whereKey($subjectId)
                ->where('user_id', $user->id)
                ->exists(),
            default => throw new InvalidArgumentException("Unsupported subject_type: {$subjectType}"),
        };

        abort_unless($owned, 404);
    }

    private function assertOwned(User $user, MoneyDecision $decision): void
    {
        abort_unless((int) $decision->user_id === (int) $user->id, 404);
    }
}
