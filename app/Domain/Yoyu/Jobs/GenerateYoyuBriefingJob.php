<?php

namespace App\Domain\Yoyu\Jobs;

use App\Domain\Kioku\Services\RecallService;
use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\PromptTemplate;
use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Domain\Yoyu\Support\MockCalendar;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateYoyuBriefingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * One AI call at up to 60s HTTP timeout, plus DB work.
     */
    public int $timeout = 90;

    /**
     * @var list<int>
     */
    public array $backoff = [15, 45];

    public function __construct(public string $briefingId) {}

    public function handle(AiGateway $ai, RecallService $recall): void
    {
        $briefing = YoyuBriefing::query()->withoutUserScope()->find($this->briefingId);
        if ($briefing === null) {
            return;
        }

        $briefing->update(['status' => 'generating']);

        $recallLines = $recall->for((int) $briefing->user_id, '朝ブリーフィング 今日の予定', 5);
        $calendar = MockCalendar::todayEvents();
        $hand = MockCalendar::clearDawnHand();

        $context = "予定:\n".collect($calendar)->map(function (array $e): string {
            $start = Carbon::parse($e['start'])->format('H:i');

            return "- {$e['title']} {$start}";
        })->implode("\n")."\n"
            ."Clear Dawnの一手: {$hand['action']}\n"
            ."過去の経験:\n".implode("\n", $recallLines);

        try {
            $result = $ai->complete(
                userId: (int) $briefing->user_id,
                feature: 'yoyu.briefing',
                prompt: PromptTemplate::make(
                    'yoyu.briefing.v1',
                    'あなたは優しい秘書ヨユウです。急かさない口調で朝ブリーフィングを作ります。',
                    "形式:\n■ 今日の全体像\n■ 最も注意する時刻\n■ 夢に向かう一手\n■ 過去のパターンに基づく注意\n■ 手放していいこと\n220文字以内。\n\n{$context}",
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
