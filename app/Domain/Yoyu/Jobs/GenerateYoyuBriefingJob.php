<?php

namespace App\Domain\Yoyu\Jobs;

use App\Domain\Connectors\Calendar\CalendarConnectionStatus;
use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\PromptTemplate;
use App\Domain\Yoyu\Data\ClearDawnHand;
use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Domain\Yoyu\Services\BriefingContextBuilder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateYoyuBriefingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    /**
     * One AI call at up to 60s HTTP timeout, plus DB work / sync_pending waits.
     */
    public int $timeout = 90;

    /**
     * Short delays while waiting for first calendar sync (≈60s total).
     *
     * @var list<int>
     */
    public array $backoff = [5, 5, 10, 10, 10, 10, 15];

    public function __construct(public string $briefingId) {}

    public function handle(AiGateway $ai, BriefingContextBuilder $contexts): void
    {
        $briefing = YoyuBriefing::query()->withoutUserScope()->find($this->briefingId);
        if ($briefing === null) {
            return;
        }

        $user = User::query()->find($briefing->user_id);
        if ($user === null) {
            return;
        }

        $briefing->update(['status' => 'generating']);

        $date = CarbonImmutable::parse($briefing->date->toDateString());
        $context = $contexts->build($user, $date);

        // Connected but never synced: wait briefly for SyncCalendarJob (no Google HTTP here).
        if (
            $context->calendar->connectionStatus === CalendarConnectionStatus::Syncing
            || $context->calendar->warningCode === 'sync_pending'
        ) {
            if ($context->calendar->syncedAt === null && $context->calendar->events === []) {
                if ($this->attempts() < 7) {
                    $briefing->update(['status' => 'pending']);
                    $this->release($this->backoff[$this->attempts() - 1] ?? 10);

                    return;
                }
                // Fall through: empty schedule + sync_pending warning, still generate deterministic context text.
            }
        }

        $hand = $context->hand;
        $handLabel = $hand instanceof ClearDawnHand
            ? $hand->title
            : '（未設定）';
        $calendarLines = collect($context->calendar->timedEvents())->map(function ($e) use ($context): string {
            $start = $e->startsAt?->timezone($context->timezone)->format('H:i') ?? '?';

            return "- {$e->title} {$start}";
        })->implode("\n");

        if ($calendarLines === '') {
            $calendarLines = '- （予定なし）';
        }

        $gapLines = collect($context->gaps->suggestibleGaps)
            ->map(fn ($gap): string => "- {$gap->key}: ".$gap->start->timezone($context->timezone)->format('H:i')
                .'-'.$gap->end->timezone($context->timezone)->format('H:i')
                ." ({$gap->minutes}分)")
            ->implode("\n");

        $taskLines = $context->tasks
            ->map(fn ($t): string => "- {$t->title}（{$t->estimate_minutes}分）")
            ->implode("\n");

        $contextText = "予定:\n{$calendarLines}\n"
            ."空き時間:\n".($gapLines !== '' ? $gapLines : '- （30分以上の空きなし）')."\n"
            ."余裕メーター: {$context->margin->marginLabel}（{$context->margin->marginScore}）\n"
            ."Clear Dawnの一手: {$handLabel}\n"
            ."未完了タスク:\n".($taskLines !== '' ? $taskLines : '- （なし）')."\n"
            ."過去の経験:\n".implode("\n", $context->recallLines);

        if ($context->calendar->warningCode !== null) {
            $contextText .= "\nカレンダー警告: {$context->calendar->warningCode}";
        }

        try {
            $result = $ai->complete(
                userId: (int) $briefing->user_id,
                feature: 'yoyu.briefing',
                prompt: PromptTemplate::make(
                    'yoyu.briefing.v1',
                    'あなたは優しい秘書ヨユウです。急かさない口調で朝ブリーフィングを作ります。',
                    "形式:\n■ 今日の全体像\n■ 最も注意する時刻\n■ 夢に向かう一手\n■ 過去のパターンに基づく注意\n■ 手放していいこと\n220文字以内。\n\n{$contextText}",
                ),
                tier: 'cheap',
                maxTokens: 600,
            );
            $body = trim($result['text']);

            $briefing->update([
                'body' => $body !== '' ? $body : $briefing->body,
                'status' => 'ready',
            ]);
        } catch (Throwable $e) {
            Log::warning('GenerateYoyuBriefingJob failed', [
                'briefing_id' => $this->briefingId,
                'message' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $briefing->update(['status' => 'failed']);

                return;
            }

            $briefing->update(['status' => 'pending']);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        YoyuBriefing::query()
            ->withoutUserScope()
            ->whereKey($this->briefingId)
            ->whereIn('status', ['pending', 'generating'])
            ->update(['status' => 'failed']);
    }
}
