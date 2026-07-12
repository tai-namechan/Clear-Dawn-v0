<?php

namespace App\Domain\Yoyu\Services;

use App\Domain\Yoyu\Data\BriefingMemoryRef;
use App\Domain\Yoyu\Exceptions\InvalidBriefingResponseException;

/**
 * Strict server-side parser for yoyu.briefing.v2 AI output.
 * Never trusts AI titles/times/IDs — joins allowlist keys to server data.
 *
 * Accepted raw forms only:
 * - entire response is a JSON object
 * - entire response is a single ``` / ```json fenced JSON object
 */
final class BriefingResponseParser
{
    /**
     * @param  array{
     *     events: array<string, array{title: string, start: string, end: string}>,
     *     gaps: array<string, array{start: string, end: string, minutes: int}>,
     *     memories: array<string, BriefingMemoryRef>,
     *     hand_key: string|null,
     *     tasks: array<string, array{title: string, estimate_minutes: int}>
     * }  $allowlist
     * @return array{
     *     overview: string,
     *     caution: array{event_key: string|null, reason: string|null, event: array{title: string, start: string, end: string}|null},
     *     hand_note: string|null,
     *     gap_suggestions: list<array{gap_key: string, suggestion: string, start: string, end: string, minutes: int}>,
     *     let_go: string,
     *     pattern_note: array{text: string, memory_keys: list<string>, memories: list<array{key: string, id: string, title: string, url: string}>}|null
     * }
     */
    public function parse(string $raw, array $allowlist): array
    {
        $decoded = $this->decodeObject($raw);

        $this->assertOnlyKnownKeys($decoded, [
            'overview',
            'caution',
            'hand_note',
            'gap_suggestions',
            'let_go',
            'pattern_note',
        ]);

        $overview = $this->requirePlainString($decoded['overview'] ?? null, 'overview', BriefingPromptBuilder::OVERVIEW_MAX);
        $letGo = $this->requirePlainString($decoded['let_go'] ?? null, 'let_go', BriefingPromptBuilder::LET_GO_MAX);

        $caution = $this->parseCaution($decoded['caution'] ?? null, $allowlist['events']);
        $handNote = $this->parseHandNote($decoded['hand_note'] ?? null, $allowlist['hand_key']);
        $gapSuggestions = $this->parseGapSuggestions($decoded['gap_suggestions'] ?? null, $allowlist['gaps']);
        $patternNote = $this->parsePatternNote($decoded['pattern_note'] ?? null, $allowlist['memories']);

        return [
            'overview' => $overview,
            'caution' => $caution,
            'hand_note' => $handNote,
            'gap_suggestions' => $gapSuggestions,
            'let_go' => $letGo,
            'pattern_note' => $patternNote,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeObject(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw new InvalidBriefingResponseException('Briefing AI response is empty.');
        }

        // Only a single full-response code fence is allowed (no prose outside).
        if (preg_match('/^```(?:json)?\s*\n?(.*?)\n?\s*```$/is', $trimmed, $fence) === 1) {
            $trimmed = trim($fence[1]);
        }

        // Reject any leftover fence markers or non-object wrappers (no {...} extraction).
        if ($trimmed === '' || ! str_starts_with($trimmed, '{') || ! str_ends_with($trimmed, '}')) {
            throw new InvalidBriefingResponseException('Briefing AI response must be a JSON object (or a single JSON code fence).');
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidBriefingResponseException('Briefing AI response is not valid JSON.', 0, $e);
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidBriefingResponseException('Briefing AI response must be a JSON object.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  list<string>  $allowed
     */
    private function assertOnlyKnownKeys(array $decoded, array $allowed): void
    {
        $unknown = array_diff(array_keys($decoded), $allowed);
        if ($unknown !== []) {
            throw new InvalidBriefingResponseException('Briefing AI response contains unknown keys.');
        }
    }

    /**
     * @param  array<string, array{title: string, start: string, end: string}>  $events
     * @return array{event_key: string|null, reason: string|null, event: array{title: string, start: string, end: string}|null}
     */
    private function parseCaution(mixed $caution, array $events): array
    {
        if ($caution === null) {
            return ['event_key' => null, 'reason' => null, 'event' => null];
        }

        if (! is_array($caution) || array_is_list($caution)) {
            throw new InvalidBriefingResponseException('caution must be an object or null.');
        }

        $this->assertOnlyKnownKeys($caution, ['event_key', 'reason']);

        $eventKey = $caution['event_key'] ?? null;
        $reason = $caution['reason'] ?? null;

        if ($eventKey !== null && ! is_string($eventKey)) {
            throw new InvalidBriefingResponseException('caution.event_key must be string or null.');
        }

        if ($eventKey === '' || ($eventKey !== null && ! array_key_exists($eventKey, $events))) {
            $eventKey = null;
        }

        $reasonText = null;
        if ($reason !== null) {
            $reasonText = $this->requirePlainString($reason, 'caution.reason', BriefingPromptBuilder::CAUTION_REASON_MAX, allowEmpty: true);
            if ($reasonText === '') {
                $reasonText = null;
            }
        }

        if ($eventKey === null) {
            $reasonText = null;
        }

        return [
            'event_key' => $eventKey,
            'reason' => $reasonText,
            'event' => $eventKey !== null ? $events[$eventKey] : null,
        ];
    }

    private function parseHandNote(mixed $handNote, ?string $handKey): ?string
    {
        if ($handKey === null) {
            return null;
        }

        if ($handNote === null) {
            return null;
        }

        $text = $this->requirePlainString($handNote, 'hand_note', BriefingPromptBuilder::HAND_NOTE_MAX, allowEmpty: true);

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, array{start: string, end: string, minutes: int}>  $gaps
     * @return list<array{gap_key: string, suggestion: string, start: string, end: string, minutes: int}>
     */
    private function parseGapSuggestions(mixed $suggestions, array $gaps): array
    {
        if ($suggestions === null) {
            return [];
        }

        if (! is_array($suggestions) || ! array_is_list($suggestions)) {
            throw new InvalidBriefingResponseException('gap_suggestions must be an array.');
        }

        $byGap = [];
        foreach ($suggestions as $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw new InvalidBriefingResponseException('gap_suggestions items must be objects.');
            }
            $this->assertOnlyKnownKeys($row, ['gap_key', 'suggestion']);

            $gapKey = $row['gap_key'] ?? null;
            if (! is_string($gapKey) || ! array_key_exists($gapKey, $gaps)) {
                continue;
            }

            $suggestion = $this->requirePlainString(
                $row['suggestion'] ?? null,
                'gap_suggestions.suggestion',
                BriefingPromptBuilder::GAP_SUGGESTION_MAX,
            );

            if (array_key_exists($gapKey, $byGap)) {
                continue;
            }

            $byGap[$gapKey] = [
                'gap_key' => $gapKey,
                'suggestion' => $suggestion,
                'start' => $gaps[$gapKey]['start'],
                'end' => $gaps[$gapKey]['end'],
                'minutes' => $gaps[$gapKey]['minutes'],
            ];

            if (count($byGap) >= 5) {
                break;
            }
        }

        return array_values($byGap);
    }

    /**
     * @param  array<string, BriefingMemoryRef>  $memories
     * @return array{text: string, memory_keys: list<string>, memories: list<array{key: string, id: string, title: string, url: string}>}|null
     */
    private function parsePatternNote(mixed $patternNote, array $memories): ?array
    {
        if ($patternNote === null) {
            return null;
        }

        if (! is_array($patternNote) || array_is_list($patternNote)) {
            throw new InvalidBriefingResponseException('pattern_note must be an object or null.');
        }

        $this->assertOnlyKnownKeys($patternNote, ['text', 'memory_keys']);

        $text = $this->requirePlainString(
            $patternNote['text'] ?? null,
            'pattern_note.text',
            BriefingPromptBuilder::PATTERN_NOTE_MAX,
        );

        $keysRaw = $patternNote['memory_keys'] ?? null;
        if (! is_array($keysRaw) || ! array_is_list($keysRaw)) {
            throw new InvalidBriefingResponseException('pattern_note.memory_keys must be an array.');
        }

        $resolved = [];
        $keys = [];
        foreach ($keysRaw as $key) {
            if (! is_string($key) || ! array_key_exists($key, $memories)) {
                continue;
            }
            if (in_array($key, $keys, true)) {
                continue;
            }
            $keys[] = $key;
            $ref = $memories[$key];
            $resolved[] = [
                'key' => $ref->key,
                'id' => $ref->id,
                'title' => $ref->title,
                'url' => $ref->url,
            ];
        }

        if ($keys === []) {
            return null;
        }

        return [
            'text' => $text,
            'memory_keys' => $keys,
            'memories' => $resolved,
        ];
    }

    private function requirePlainString(mixed $value, string $field, int $max, bool $allowEmpty = false): string
    {
        if (! is_string($value)) {
            throw new InvalidBriefingResponseException("{$field} must be a string.");
        }

        $text = trim($value);
        if (! $allowEmpty && $text === '') {
            throw new InvalidBriefingResponseException("{$field} must not be empty.");
        }

        if (mb_strlen($text) > $max) {
            throw new InvalidBriefingResponseException("{$field} exceeds max length {$max}.");
        }

        if ($this->containsHtml($text)) {
            throw new InvalidBriefingResponseException("{$field} must not contain HTML.");
        }

        return $text;
    }

    private function containsHtml(string $text): bool
    {
        return $text !== strip_tags($text);
    }
}
