<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Models\KiokuLetterItem;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\PromptTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Generates one weekly concierge letter
 * (docs/product/kioku-final-remaining-implementation.md §13).
 *
 * The AI receives candidate summaries only and returns 0–5 items; invalid
 * items are dropped without a top-up regeneration. Zero valid items is the
 * 'empty' outcome (a correct result), while AI/transport failures are
 * 'failed' — the two are never conflated. Character variants share the
 * candidates, prompt, and body; the variant only tags presentation.
 */
final class KiokuLetterGenerator
{
    public const PROMPT_KEY = 'kioku.concierge.letter.v1';

    public const FEATURE = 'kioku.concierge.letter';

    private const MAX_ITEMS = 5;

    private const HEADLINE_MAX_CHARS = 60;

    private const WHY_NOW_MAX_CHARS = 180;

    private const RELATED_MAX = 2;

    public function __construct(
        private AiGateway $ai,
        private KiokuLetterCandidateService $candidates,
    ) {}

    public function generate(
        User $user,
        CarbonImmutable $weekStart,
        string $characterVariant,
        ?string $context,
    ): KiokuLetter {
        if (! in_array($characterVariant, KiokuLetter::CHARACTER_VARIANTS, true)) {
            throw new KiokuLetterException("Unknown character variant [{$characterVariant}]. Use shiori or nagi.");
        }

        $weekStart = $weekStart->startOfWeek();
        $candidates = $this->candidates->candidatesFor((int) $user->id);

        try {
            $letter = KiokuLetter::query()->create([
                'user_id' => $user->id,
                'week_start' => $weekStart->toDateString(),
                'status' => KiokuLetter::STATUS_GENERATING,
                'character_variant' => $characterVariant,
                'context' => $context !== null && trim($context) !== '' ? trim($context) : null,
                'candidate_count' => $candidates->count(),
                'prompt_key' => self::PROMPT_KEY,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new KiokuLetterException(
                "A letter already exists for user {$user->id} / week {$weekStart->toDateString()}. One letter per user per week; it is never overwritten."
            );
        }

        if ($candidates->isEmpty()) {
            $letter->update([
                'status' => KiokuLetter::STATUS_EMPTY,
                'generated_at' => now(),
                'published_at' => now(),
            ]);

            return $letter->refresh();
        }

        try {
            $result = $this->ai->complete(
                userId: (int) $user->id,
                feature: self::FEATURE,
                prompt: $this->prompt($weekStart, $context, $candidates),
                tier: 'strong',
                maxTokens: 1600,
            );
        } catch (Throwable $e) {
            $letter->update([
                'status' => KiokuLetter::STATUS_FAILED,
                'generated_at' => now(),
            ]);

            throw new KiokuLetterException('Letter generation failed: '.$e->getMessage(), 0, $e);
        }

        $decoded = $this->decodeJson($result['text']);
        if ($decoded === null || ! is_array($decoded['items'] ?? null)) {
            $letter->update([
                'status' => KiokuLetter::STATUS_FAILED,
                'model' => $result['model'],
                'generation_meta' => ['usage_request_id' => $result['usage_request_id']],
                'generated_at' => now(),
            ]);

            throw new KiokuLetterException('Letter generation returned an unusable response (not the expected JSON).');
        }

        $items = $this->validItems(array_values($decoded['items']), $candidates);
        $intro = is_string($decoded['intro'] ?? null) ? trim($decoded['intro']) : null;

        DB::transaction(function () use ($letter, $items, $intro, $result): void {
            foreach ($items as $index => $item) {
                KiokuLetterItem::query()->create([
                    'letter_id' => $letter->id,
                    'memory_id' => $item['memory']->id,
                    'position' => $index + 1,
                    'title_snapshot' => $item['memory']->title,
                    'summary_snapshot' => $item['memory']->summary,
                    'headline' => $item['headline'],
                    'why_now' => $item['why_now'],
                    'related_memory_ids' => $item['related_memory_ids'] === [] ? null : $item['related_memory_ids'],
                ]);
            }

            $letter->update([
                'status' => $items === [] ? KiokuLetter::STATUS_EMPTY : KiokuLetter::STATUS_PUBLISHED,
                'intro' => $intro !== '' ? $intro : null,
                'item_count' => count($items),
                'model' => $result['model'],
                'generation_meta' => ['usage_request_id' => $result['usage_request_id']],
                'generated_at' => now(),
                'published_at' => now(),
            ]);
        });

        return $letter->refresh();
    }

    /**
     * Prompt v1. Character tone must never leak into the AI body — the
     * shiori/nagi difference is presentation only.
     *
     * @param  Collection<int, Memory>  $candidates
     */
    public function prompt(CarbonImmutable $weekStart, ?string $context, Collection $candidates): PromptTemplate
    {
        $candidatesJson = json_encode(
            $this->candidates->candidatePayload($candidates),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        $contextText = $context !== null && trim($context) !== '' ? trim($context) : '（特になし）';
        $today = now()->toDateString();

        return PromptTemplate::make(
            self::PROMPT_KEY,
            'あなたは個人の記憶アーカイブから「今もう一度見る価値がある記憶」を選ぶ編集者です。JSONのみで返答します。',
            <<<PROMPT
今週のコンシェルジュ手紙を作ります。候補の記憶一覧から、今の文脈に照らして「忘れられているが、今差し込む価値がある」ものを最大5件選んでください。

厳守事項:
- 確信の持てない記憶を混ぜるくらいなら件数を減らす。0件でもよい
- 各項目の why_now には「なぜ今この記憶か」を候補の内容と文脈に基づいて具体的に書く。こじつけは禁止
- 入力にない事実を作らない
- 挨拶・締めの励まし・定型文を書かない
- intro は最大2文で、今週の記憶全体から見えることだけを書く
- headline は最大60文字、why_now は最大180文字
- related_memory_ids は候補一覧の id のみ、最大2件。無ければ空配列

今日の日付: {$today}
対象週: {$weekStart->toDateString()} の週

今週の文脈:
{$contextText}

候補（JSON）:
{$candidatesJson}

出力（JSONのみ）:
{"schema_version":1,"intro":"...","items":[{"memory_id":"候補のid","headline":"...","why_now":"...","related_memory_ids":[]}]}
PROMPT,
        );
    }

    /**
     * Server-side validation (§13): drop invalid items, never regenerate to
     * fill the count. Candidate-set membership doubles as the sensitive
     * re-check because sensitive memories can never enter the set.
     *
     * @param  list<mixed>  $rawItems
     * @param  Collection<int, Memory>  $candidates
     * @return list<array{memory: Memory, headline: string, why_now: string, related_memory_ids: list<string>}>
     */
    private function validItems(array $rawItems, Collection $candidates): array
    {
        $byId = $candidates->keyBy('id');
        $items = [];
        $usedMemoryIds = [];

        foreach ($rawItems as $raw) {
            if (count($items) >= self::MAX_ITEMS) {
                break;
            }

            if (! is_array($raw)) {
                continue;
            }

            $memoryId = $raw['memory_id'] ?? null;
            if (! is_string($memoryId) || isset($usedMemoryIds[$memoryId])) {
                continue;
            }

            /** @var Memory|null $memory */
            $memory = $byId->get($memoryId);
            if ($memory === null || $memory->sensitive || $memory->status !== 'ready') {
                continue;
            }

            $headline = is_string($raw['headline'] ?? null) ? trim($raw['headline']) : '';
            if ($headline === '' || mb_strlen($headline) > self::HEADLINE_MAX_CHARS) {
                continue;
            }

            $whyNow = is_string($raw['why_now'] ?? null) ? trim($raw['why_now']) : '';
            if ($whyNow === '' || mb_strlen($whyNow) > self::WHY_NOW_MAX_CHARS) {
                continue;
            }

            $related = [];
            foreach (is_array($raw['related_memory_ids'] ?? null) ? $raw['related_memory_ids'] : [] as $relatedId) {
                if (! is_string($relatedId) || $relatedId === $memoryId || ! $byId->has($relatedId)) {
                    continue;
                }
                if (in_array($relatedId, $related, true) || count($related) >= self::RELATED_MAX) {
                    continue;
                }
                $related[] = $relatedId;
            }

            $usedMemoryIds[$memoryId] = true;
            $items[] = [
                'memory' => $memory,
                'headline' => $headline,
                'why_now' => $whyNow,
                'related_memory_ids' => $related,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $text): ?array
    {
        $trimmed = trim($text);
        if (preg_match('/\{.*\}/s', $trimmed, $matches) === 1) {
            $trimmed = $matches[0];
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }
}
