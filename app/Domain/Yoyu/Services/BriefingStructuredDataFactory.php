<?php

namespace App\Domain\Yoyu\Services;

use App\Domain\Yoyu\Data\BriefingContext;
use App\Domain\Yoyu\Data\ClearDawnHand;
use App\Domain\Yoyu\Data\GapSlot;

/**
 * Builds schema_version=2 structured_data payloads from deterministic context + generation.
 */
class BriefingStructuredDataFactory
{
    public const SCHEMA_VERSION = 2;

    /**
     * @param  array<string, mixed>|null  $generation  Parsed generation block (status + AI fields)
     * @return array<string, mixed>
     */
    public function make(BriefingContext $context, ?array $generation): array
    {
        $gaps = array_map(
            function (GapSlot $gap) use ($context): array {
                return [
                    'key' => $gap->key,
                    'start' => $gap->start->timezone($context->timezone)->format('H:i'),
                    'end' => $gap->end->timezone($context->timezone)->format('H:i'),
                    'minutes' => $gap->minutes,
                ];
            },
            $context->gaps->suggestibleGaps,
        );

        $hand = null;
        if ($context->hand instanceof ClearDawnHand) {
            $hand = [
                'id' => $context->hand->id,
                'title' => $context->hand->title,
                'life_area' => $context->hand->lifeAreaName,
            ];
        }

        $generationPayload = $generation ?? [
            'status' => 'pending',
            'overview' => null,
            'caution' => null,
            'hand_note' => null,
            'gap_suggestions' => [],
            'let_go' => null,
            'pattern_note' => null,
        ];

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'briefing_date' => $context->briefingDate,
            'timezone' => $context->timezone,
            'calendar' => [
                'connection_status' => $context->calendar->connectionStatus->value,
                'synced_at' => $context->calendar->syncedAt?->toIso8601String(),
                'is_stale' => $context->calendar->isStale,
                'warning_code' => $context->calendar->warningCode,
            ],
            'analysis' => [
                'busy_minutes' => $context->margin->busyMinutes,
                'task_minutes' => $context->margin->taskMinutes,
                'working_minutes' => $context->margin->workingMinutes,
                'margin_score' => $context->margin->marginScore,
                'margin_label' => $context->margin->marginLabel,
                'gaps' => $gaps,
            ],
            'hand' => $hand,
            'generation' => $generationPayload,
        ];
    }

    /**
     * Plain-text body fallback for legacy UI / non-v2 clients.
     *
     * @param  array<string, mixed>  $structured
     */
    public function bodyFromStructured(array $structured): string
    {
        $generation = is_array($structured['generation'] ?? null) ? $structured['generation'] : [];
        $lines = [];

        $overview = $generation['overview'] ?? null;
        if (is_string($overview) && $overview !== '') {
            $lines[] = '■ 今日の全体像';
            $lines[] = $overview;
        }

        $caution = is_array($generation['caution'] ?? null) ? $generation['caution'] : null;
        if (is_array($caution) && is_string($caution['reason'] ?? null) && $caution['reason'] !== '') {
            $event = is_array($caution['event'] ?? null) ? $caution['event'] : null;
            $label = is_array($event) && is_string($event['title'] ?? null)
                ? ((string) ($event['start'] ?? '')).' '.(string) $event['title']
                : '';
            $lines[] = '■ 注意する予定'.($label !== '' ? "（{$label}）" : '');
            $lines[] = (string) $caution['reason'];
        }

        $handNote = $generation['hand_note'] ?? null;
        $hand = is_array($structured['hand'] ?? null) ? $structured['hand'] : null;
        if (is_string($handNote) && $handNote !== '') {
            $handTitle = is_array($hand) ? (string) ($hand['title'] ?? '') : '';
            $lines[] = '■ 夢に向かう一手'.($handTitle !== '' ? "（{$handTitle}）" : '');
            $lines[] = $handNote;
        }

        $suggestions = is_array($generation['gap_suggestions'] ?? null) ? $generation['gap_suggestions'] : [];
        if ($suggestions !== []) {
            $lines[] = '■ 空き時間の提案';
            foreach ($suggestions as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $start = (string) ($row['start'] ?? '');
                $end = (string) ($row['end'] ?? '');
                $suggestion = (string) ($row['suggestion'] ?? '');
                if ($suggestion === '') {
                    continue;
                }
                $lines[] = "- {$start}-{$end}: {$suggestion}";
            }
        }

        $letGo = $generation['let_go'] ?? null;
        if (is_string($letGo) && $letGo !== '') {
            $lines[] = '■ 手放していいこと';
            $lines[] = $letGo;
        }

        $pattern = is_array($generation['pattern_note'] ?? null) ? $generation['pattern_note'] : null;
        if (is_array($pattern) && is_string($pattern['text'] ?? null) && $pattern['text'] !== '') {
            $lines[] = '■ 過去のパターン';
            $lines[] = (string) $pattern['text'];
        }

        $status = (string) ($generation['status'] ?? '');
        if ($lines === []) {
            return match ($status) {
                'quota_limited' => '今月のAI利用上限に達したため、文章の生成はスキップしました。予定の分析結果は表示できます。',
                'invalid_response' => 'AIの応答を検証できなかったため、文章は表示できません。予定の分析結果は表示できます。',
                default => '朝ブリーフィングを準備しています…',
            };
        }

        return implode("\n", $lines);
    }
}
