<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\KiokuLetterCadence;
use App\Domain\Kioku\KiokuLetterMode;
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
use Illuminate\Support\Str;
use Throwable;

/**
 * Generates a concierge letter
 * (docs/product/kioku-final-remaining-implementation.md §13 +
 * docs/product/kioku-concierge-daily-pilot.md).
 *
 * The AI receives candidate summaries only. Invalid items are dropped
 * without top-up regeneration. Zero valid items is 'empty'; AI/transport
 * failures are 'failed'. Live publish updates last_delivered_at in the
 * same transaction.
 */
final class KiokuLetterGenerator
{
    public const PROMPT_KEY = 'kioku.concierge.letter.v1';

    public const FEATURE = 'kioku.concierge.letter';

    private const HEADLINE_MAX_CHARS = 60;

    private const WHY_NOW_MAX_CHARS = 180;

    private const RELATED_MAX = 2;

    public function __construct(
        private AiGateway $ai,
        private KiokuLetterCandidateService $candidates,
        private KiokuLetterHaltGuard $haltGuard,
        private MemoryReferenceService $references,
    ) {}

    /**
     * Backward-compatible weekly live generation used by kioku:letters:generate.
     */
    public function generate(
        User $user,
        CarbonImmutable $weekStart,
        string $characterVariant,
        ?string $context,
    ): KiokuLetter {
        return $this->generateLetter(
            user: $user,
            characterVariant: $characterVariant,
            context: $context,
            mode: KiokuLetterMode::Live,
            cadence: KiokuLetterCadence::Weekly,
            deliveryDate: $weekStart->startOfWeek(),
        );
    }

    public function generateLetter(
        User $user,
        string $characterVariant,
        ?string $context,
        KiokuLetterMode $mode,
        KiokuLetterCadence $cadence,
        CarbonImmutable $deliveryDate,
        ?int $pilotDay = null,
        ?KiokuLetter $retryOf = null,
    ): KiokuLetter {
        if (! in_array($characterVariant, KiokuLetter::CHARACTER_VARIANTS, true)) {
            throw new KiokuLetterException("Unknown character variant [{$characterVariant}]. Use shiori or nagi.");
        }

        // Halt check before candidate query, AI call, or letter row creation.
        $this->haltGuard->assertGenerationAllowed((int) $user->id);

        $deliveryDate = $deliveryDate->startOfDay();
        $weekStart = $cadence->weekStartFor($deliveryDate);
        $maxItems = $cadence->maxItems();

        $letter = $retryOf !== null
            ? $this->claimFailedRetry($retryOf, $user, $characterVariant, $context)
            : $this->createLetterRow(
                $user,
                $mode,
                $cadence,
                $deliveryDate,
                $weekStart,
                $characterVariant,
                $context,
                $pilotDay,
            );

        $candidates = $this->candidates->candidatesFor((int) $user->id);
        $letter->update(['candidate_count' => $candidates->count()]);

        if ($candidates->isEmpty()) {
            $this->finalizeEmptyOrPublished($letter, [], null, null, $mode);

            return $letter->refresh();
        }

        try {
            $result = $this->ai->complete(
                userId: (int) $user->id,
                feature: self::FEATURE,
                prompt: $this->prompt($deliveryDate, $cadence, $context, $candidates, $maxItems),
                tier: 'strong',
                maxTokens: 1600,
            );
        } catch (Throwable $e) {
            $this->markFailed($letter, null, $e->getMessage());

            throw new KiokuLetterException('Letter generation failed: '.$e->getMessage(), 0, $e);
        }

        $decoded = $this->decodeJson($result['text']);
        if ($decoded === null || ! is_array($decoded['items'] ?? null)) {
            $this->markFailed($letter, [
                'usage_request_id' => $result['usage_request_id'],
                'model' => $result['model'],
            ], 'unusable JSON response');

            throw new KiokuLetterException('Letter generation returned an unusable response (not the expected JSON).');
        }

        $items = $this->validItems(array_values($decoded['items']), $candidates, $maxItems);
        $intro = is_string($decoded['intro'] ?? null) ? trim($decoded['intro']) : null;

        $this->finalizeEmptyOrPublished($letter, $items, $intro, $result, $mode);

        return $letter->refresh();
    }

    public function retryFailed(KiokuLetter $letter, ?string $context = null): KiokuLetter
    {
        $user = User::query()->findOrFail($letter->user_id);

        return $this->generateLetter(
            user: $user,
            characterVariant: $letter->character_variant,
            context: $context ?? $letter->context,
            mode: $letter->modeEnum(),
            cadence: $letter->cadenceEnum(),
            deliveryDate: CarbonImmutable::parse($letter->delivery_date->toDateString()),
            pilotDay: $letter->pilot_day,
            retryOf: $letter,
        );
    }

    /**
     * @param  Collection<int, Memory>  $candidates
     */
    public function prompt(
        CarbonImmutable $deliveryDate,
        KiokuLetterCadence $cadence,
        ?string $context,
        Collection $candidates,
        ?int $maxItems = null,
    ): PromptTemplate {
        $maxItems ??= $cadence->maxItems();
        $candidatesJson = json_encode(
            $this->candidates->candidatePayload($candidates),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        $contextText = $context !== null && trim($context) !== '' ? trim($context) : '（特になし）';
        $today = now()->toDateString();
        $periodLabel = $cadence === KiokuLetterCadence::Daily
            ? "対象日: {$deliveryDate->toDateString()}"
            : "対象週: {$deliveryDate->startOfWeek()->toDateString()} の週";

        return PromptTemplate::make(
            self::PROMPT_KEY,
            'あなたは個人の記憶アーカイブから「今もう一度見る価値がある記憶」を選ぶ編集者です。JSONのみで返答します。',
            <<<PROMPT
コンシェルジュ手紙を作ります。候補の記憶一覧から、今の文脈に照らして「忘れられているが、今差し込む価値がある」ものを最大{$maxItems}件選んでください。

厳守事項:
- 確信の持てない記憶を混ぜるくらいなら件数を減らす。0件でもよい
- 各項目の why_now には「なぜ今この記憶か」を候補の内容と文脈に基づいて具体的に書く。こじつけは禁止
- 入力にない事実を作らない
- 挨拶・締めの励まし・定型文を書かない
- intro は最大2文で、記憶全体から見えることだけを書く
- headline は最大60文字、why_now は最大180文字
- related_memory_ids は候補一覧の id のみ、最大2件。無ければ空配列

今日の日付: {$today}
{$periodLabel}

文脈:
{$contextText}

候補（JSON）:
{$candidatesJson}

出力（JSONのみ）:
{"schema_version":1,"intro":"...","items":[{"memory_id":"候補のid","headline":"...","why_now":"...","related_memory_ids":[]}]}
PROMPT,
        );
    }

    private function createLetterRow(
        User $user,
        KiokuLetterMode $mode,
        KiokuLetterCadence $cadence,
        CarbonImmutable $deliveryDate,
        CarbonImmutable $weekStart,
        string $characterVariant,
        ?string $context,
        ?int $pilotDay,
    ): KiokuLetter {
        $dedupeKey = $mode === KiokuLetterMode::Test
            ? 'test:'.(string) Str::ulid()
            : $cadence->dedupeKeyFor($deliveryDate);

        try {
            return KiokuLetter::query()->create([
                'user_id' => $user->id,
                'week_start' => $weekStart->toDateString(),
                'mode' => $mode->value,
                'cadence' => $cadence->value,
                'delivery_date' => $deliveryDate->toDateString(),
                'dedupe_key' => $dedupeKey,
                'pilot_day' => $pilotDay,
                'status' => KiokuLetter::STATUS_GENERATING,
                'character_variant' => $characterVariant,
                'context' => $context !== null && trim($context) !== '' ? trim($context) : null,
                'candidate_count' => 0,
                'prompt_key' => self::PROMPT_KEY,
                'test_expires_at' => $mode === KiokuLetterMode::Test ? now()->addDays(7) : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new KiokuLetterException(
                "A live letter already exists for user {$user->id} / {$dedupeKey}. It is never overwritten."
            );
        }
    }

    private function claimFailedRetry(
        KiokuLetter $letter,
        User $user,
        string $characterVariant,
        ?string $context,
    ): KiokuLetter {
        if ((int) $letter->user_id !== (int) $user->id) {
            throw new KiokuLetterException('Cannot retry a letter that belongs to another user.');
        }

        $claimed = KiokuLetter::query()
            ->withoutUserScope()
            ->whereKey($letter->id)
            ->where('status', KiokuLetter::STATUS_FAILED)
            ->update([
                'status' => KiokuLetter::STATUS_GENERATING,
                'retry_count' => DB::raw('retry_count + 1'),
                'character_variant' => $characterVariant,
                'context' => $context !== null && trim($context) !== '' ? trim($context) : $letter->context,
                'intro' => null,
                'item_count' => 0,
                'model' => null,
                'generated_at' => null,
                'published_at' => null,
            ]);

        if ($claimed !== 1) {
            throw new KiokuLetterException(
                "Letter {$letter->id} is not retryable. Only status=failed letters can be retried; published/empty/evaluating/evaluated/halted are never overwritten."
            );
        }

        // Drop any stale items from a partial prior attempt (should be none for failed).
        KiokuLetterItem::query()->where('letter_id', $letter->id)->delete();

        return $letter->refresh();
    }

    /**
     * @param  list<array{memory: Memory, headline: string, why_now: string, related_memory_ids: list<string>}>  $items
     * @param  array{model?: string, usage_request_id?: string}|null  $result
     */
    private function finalizeEmptyOrPublished(
        KiokuLetter $letter,
        array $items,
        ?string $intro,
        ?array $result,
        KiokuLetterMode $mode,
    ): void {
        DB::transaction(function () use ($letter, $items, $intro, $result, $mode): void {
            /** @var KiokuLetter $locked */
            $locked = KiokuLetter::query()
                ->withoutUserScope()
                ->whereKey($letter->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== KiokuLetter::STATUS_GENERATING) {
                throw new KiokuLetterException("Letter {$locked->id} is no longer generating; refusing to publish.");
            }

            foreach ($items as $index => $item) {
                KiokuLetterItem::query()->create([
                    'letter_id' => $locked->id,
                    'memory_id' => $item['memory']->id,
                    'position' => $index + 1,
                    'title_snapshot' => $item['memory']->title,
                    'summary_snapshot' => $item['memory']->summary,
                    'headline' => $item['headline'],
                    'why_now' => $item['why_now'],
                    'related_memory_ids' => $item['related_memory_ids'] === [] ? null : $item['related_memory_ids'],
                ]);
            }

            $locked->update([
                'status' => $items === [] ? KiokuLetter::STATUS_EMPTY : KiokuLetter::STATUS_PUBLISHED,
                'intro' => $intro !== null && $intro !== '' ? $intro : null,
                'item_count' => count($items),
                'model' => $result['model'] ?? $locked->model,
                'generation_meta' => $this->mergeMeta($locked->generation_meta, [
                    'usage_request_id' => $result['usage_request_id'] ?? null,
                ]),
                'generated_at' => now(),
                'published_at' => now(),
            ]);

            if ($mode === KiokuLetterMode::Live && $items !== []) {
                $this->references->markDelivered(
                    array_map(fn (array $item): string => $item['memory']->id, $items),
                );
            }
        });
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function markFailed(KiokuLetter $letter, ?array $meta, string $reason): void
    {
        $existing = $letter->fresh()->generation_meta ?? [];
        $failures = is_array($existing['failures'] ?? null) ? $existing['failures'] : [];
        $failures[] = [
            'at' => now()->toIso8601String(),
            'reason' => $reason,
            'usage_request_id' => $meta['usage_request_id'] ?? null,
        ];

        $letter->update([
            'status' => KiokuLetter::STATUS_FAILED,
            'model' => $meta['model'] ?? $letter->model,
            'generation_meta' => array_filter([
                ...$existing,
                'usage_request_id' => $meta['usage_request_id'] ?? ($existing['usage_request_id'] ?? null),
                'failures' => $failures,
            ], fn ($v) => $v !== null),
            'generated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $existing
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function mergeMeta(?array $existing, array $patch): array
    {
        return array_filter([
            ...($existing ?? []),
            ...$patch,
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  list<mixed>  $rawItems
     * @param  Collection<int, Memory>  $candidates
     * @return list<array{memory: Memory, headline: string, why_now: string, related_memory_ids: list<string>}>
     */
    private function validItems(array $rawItems, Collection $candidates, int $maxItems): array
    {
        $byId = $candidates->keyBy('id');
        $items = [];
        $usedMemoryIds = [];

        foreach ($rawItems as $raw) {
            if (count($items) >= $maxItems) {
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
