<?php

namespace Tests\Unit\Yoyu;

use App\Domain\Yoyu\Data\BriefingMemoryRef;
use App\Domain\Yoyu\Services\BriefingPromptBuilder;
use App\Domain\Yoyu\Services\BriefingResponseParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BriefingResponseParserTest extends TestCase
{
    private BriefingResponseParser $parser;

    /**
     * @var array{
     *     events: array<string, array{title: string, start: string, end: string}>,
     *     gaps: array<string, array{start: string, end: string, minutes: int}>,
     *     memories: array<string, BriefingMemoryRef>,
     *     hand_key: string|null,
     *     tasks: array<string, array{title: string, estimate_minutes: int}>
     * }
     */
    private array $allowlist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BriefingResponseParser;
        $this->allowlist = [
            'events' => [
                'event_1' => ['title' => '会議', 'start' => '10:00', 'end' => '11:00'],
            ],
            'gaps' => [
                'gap_1' => ['start' => '11:00', 'end' => '12:30', 'minutes' => 90],
                'gap_2' => ['start' => '14:00', 'end' => '15:00', 'minutes' => 60],
            ],
            'memories' => [
                'memory_1' => new BriefingMemoryRef(
                    'memory_1',
                    '01MEM1',
                    '過去の失敗',
                    '急ぎすぎた',
                    '/kioku/memories/01MEM1',
                ),
            ],
            'hand_key' => 'hand_1',
            'tasks' => [
                'task_1' => ['title' => '書類', 'estimate_minutes' => 30],
            ],
        ];
    }

    public function test_parses_valid_json_and_joins_server_authoritative_fields(): void
    {
        $raw = json_encode([
            'overview' => '今日は穏やかです。',
            'caution' => ['event_key' => 'event_1', 'reason' => '準備に余裕を。'],
            'hand_note' => '午前中に少し進める',
            'gap_suggestions' => [
                ['gap_key' => 'gap_1', 'suggestion' => '深呼吸'],
            ],
            'let_go' => '完璧主義',
            'pattern_note' => [
                'text' => '前回と同じ焦りに注意',
                'memory_keys' => ['memory_1'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $parsed = $this->parser->parse((string) $raw, $this->allowlist);

        $this->assertSame('今日は穏やかです。', $parsed['overview']);
        $this->assertSame('event_1', $parsed['caution']['event_key']);
        $this->assertSame('会議', $parsed['caution']['event']['title']);
        $this->assertSame('10:00', $parsed['caution']['event']['start']);
        $this->assertSame('11:00', $parsed['gap_suggestions'][0]['start']);
        $this->assertSame('01MEM1', $parsed['pattern_note']['memories'][0]['id']);
        $this->assertSame('/kioku/memories/01MEM1', $parsed['pattern_note']['memories'][0]['url']);
    }

    public function test_strips_code_fence(): void
    {
        $raw = "```json\n".json_encode([
            'overview' => 'OK',
            'caution' => ['event_key' => null, 'reason' => null],
            'hand_note' => null,
            'gap_suggestions' => [],
            'let_go' => '手放す',
            'pattern_note' => null,
        ], JSON_UNESCAPED_UNICODE)."\n```";

        $parsed = $this->parser->parse($raw, $this->allowlist);
        $this->assertSame('OK', $parsed['overview']);
    }

    public function test_rejects_invalid_json(): void
    {
        $this->expectException(RuntimeException::class);
        $this->parser->parse('{not-json', $this->allowlist);
    }

    public function test_rejects_non_object_json(): void
    {
        $this->expectException(RuntimeException::class);
        $this->parser->parse('["overview"]', $this->allowlist);
    }

    public function test_rejects_unknown_top_level_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->parser->parse(json_encode([
            'overview' => 'a',
            'caution' => ['event_key' => null, 'reason' => null],
            'hand_note' => null,
            'gap_suggestions' => [],
            'let_go' => 'b',
            'pattern_note' => null,
            'extra' => 'nope',
        ]), $this->allowlist);
    }

    public function test_rejects_html_in_overview(): void
    {
        $this->expectException(RuntimeException::class);
        $this->parser->parse(json_encode([
            'overview' => '<b>危険</b>',
            'caution' => ['event_key' => null, 'reason' => null],
            'hand_note' => null,
            'gap_suggestions' => [],
            'let_go' => '手放す',
            'pattern_note' => null,
        ]), $this->allowlist);
    }

    public function test_rejects_overlong_overview(): void
    {
        $this->expectException(RuntimeException::class);
        $this->parser->parse(json_encode([
            'overview' => str_repeat('あ', BriefingPromptBuilder::OVERVIEW_MAX + 1),
            'caution' => ['event_key' => null, 'reason' => null],
            'hand_note' => null,
            'gap_suggestions' => [],
            'let_go' => '手放す',
            'pattern_note' => null,
        ]), $this->allowlist);
    }

    public function test_drops_allowlist_foreign_event_and_gap_keys(): void
    {
        $parsed = $this->parser->parse(json_encode([
            'overview' => '全体',
            'caution' => ['event_key' => 'event_99', 'reason' => '無視される'],
            'hand_note' => null,
            'gap_suggestions' => [
                ['gap_key' => 'gap_99', 'suggestion' => '無視'],
                ['gap_key' => 'gap_1', 'suggestion' => '採用'],
            ],
            'let_go' => '手放す',
            'pattern_note' => null,
        ], JSON_UNESCAPED_UNICODE), $this->allowlist);

        $this->assertNull($parsed['caution']['event_key']);
        $this->assertNull($parsed['caution']['reason']);
        $this->assertCount(1, $parsed['gap_suggestions']);
        $this->assertSame('gap_1', $parsed['gap_suggestions'][0]['gap_key']);
    }

    public function test_deduplicates_gap_suggestions_and_caps_at_five(): void
    {
        $gaps = [];
        for ($i = 1; $i <= 6; $i++) {
            $gaps['gap_'.$i] = ['start' => '0'.$i.':00', 'end' => '0'.$i.':30', 'minutes' => 30];
        }
        $this->allowlist['gaps'] = $gaps;

        $suggestions = [
            ['gap_key' => 'gap_1', 'suggestion' => 'first'],
            ['gap_key' => 'gap_1', 'suggestion' => 'dup'],
            ['gap_key' => 'gap_2', 'suggestion' => 'two'],
            ['gap_key' => 'gap_3', 'suggestion' => 'three'],
            ['gap_key' => 'gap_4', 'suggestion' => 'four'],
            ['gap_key' => 'gap_5', 'suggestion' => 'five'],
            ['gap_key' => 'gap_6', 'suggestion' => 'six'],
        ];

        $parsed = $this->parser->parse(json_encode([
            'overview' => '全体',
            'caution' => ['event_key' => null, 'reason' => null],
            'hand_note' => null,
            'gap_suggestions' => $suggestions,
            'let_go' => '手放す',
            'pattern_note' => null,
        ], JSON_UNESCAPED_UNICODE), $this->allowlist);

        $this->assertCount(5, $parsed['gap_suggestions']);
        $this->assertSame('first', $parsed['gap_suggestions'][0]['suggestion']);
        $this->assertSame('gap_5', $parsed['gap_suggestions'][4]['gap_key']);
    }

    public function test_pattern_note_without_valid_memory_keys_becomes_null(): void
    {
        $parsed = $this->parser->parse(json_encode([
            'overview' => '全体',
            'caution' => ['event_key' => null, 'reason' => null],
            'hand_note' => null,
            'gap_suggestions' => [],
            'let_go' => '手放す',
            'pattern_note' => [
                'text' => '根拠なし',
                'memory_keys' => ['memory_99'],
            ],
        ], JSON_UNESCAPED_UNICODE), $this->allowlist);

        $this->assertNull($parsed['pattern_note']);
    }

    public function test_ai_cannot_override_event_title_via_extra_fields(): void
    {
        // Unknown keys inside caution are rejected (strict), proving we do not cast AI fields through.
        $this->expectException(RuntimeException::class);
        $this->parser->parse(json_encode([
            'overview' => '全体',
            'caution' => [
                'event_key' => 'event_1',
                'reason' => '注意',
                'title' => 'AIが捏造したタイトル',
            ],
            'hand_note' => null,
            'gap_suggestions' => [],
            'let_go' => '手放す',
            'pattern_note' => null,
        ], JSON_UNESCAPED_UNICODE), $this->allowlist);
    }

    public function test_hand_note_null_when_no_hand(): void
    {
        $this->allowlist['hand_key'] = null;
        $parsed = $this->parser->parse(json_encode([
            'overview' => '全体',
            'caution' => ['event_key' => null, 'reason' => null],
            'hand_note' => '無視される',
            'gap_suggestions' => [],
            'let_go' => '手放す',
            'pattern_note' => null,
        ], JSON_UNESCAPED_UNICODE), $this->allowlist);

        $this->assertNull($parsed['hand_note']);
    }

    #[DataProvider('missingRequiredProvider')]
    public function test_rejects_missing_required_fields(array $payload): void
    {
        $this->expectException(RuntimeException::class);
        $this->parser->parse(json_encode($payload, JSON_UNESCAPED_UNICODE), $this->allowlist);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function missingRequiredProvider(): array
    {
        $base = [
            'overview' => '全体',
            'caution' => ['event_key' => null, 'reason' => null],
            'hand_note' => null,
            'gap_suggestions' => [],
            'let_go' => '手放す',
            'pattern_note' => null,
        ];

        return [
            'missing overview' => [array_diff_key($base, ['overview' => true])],
            'missing let_go' => [array_diff_key($base, ['let_go' => true])],
            'empty overview' => [array_merge($base, ['overview' => '  '])],
            'wrong overview type' => [array_merge($base, ['overview' => 1])],
        ];
    }
}
