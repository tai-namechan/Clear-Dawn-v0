<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Types\MemoryTypeRegistry;
use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\PromptTemplate;

/**
 * Classifies a memory's raw content into memory_type / title / tags / importance.
 *
 * Single source of truth for the classify prompt so that EnrichMemoryJob and
 * the kioku:eval-classify command always evaluate the exact same prompt.
 */
final class MemoryClassifier
{
    public function __construct(
        private MemoryTypeRegistry $registry,
    ) {}

    /**
     * @return array{memory_type: string, title: string|null, tags: list<string>, importance: int}
     */
    public function classify(AiGateway $ai, int $userId, string $rawContent): array
    {
        $result = $ai->complete(
            userId: $userId,
            feature: 'kioku.classify',
            prompt: $this->prompt($rawContent),
            tier: 'cheap',
            maxTokens: 400,
        );

        return $this->normalize($this->decodeJson($result['text']));
    }

    public function prompt(string $rawContent): PromptTemplate
    {
        return PromptTemplate::make(
            'classify.v2',
            'You classify personal memories. Reply with JSON only.',
            <<<PROMPT
次の記憶を分類してください。JSONのみ返答:
{"memory_type":"...","importance":1-5,"tags":["..."],"title":"短いタイトル"}

memory_typeは以下から1つ選ぶ:
- thought: 考え・気づき・反省・日常生活での失敗やうっかり忘れを含む雑多なメモ
- emotion: 感情の記録（嬉しい・不安・怒りなど、気持ちそのものが主題）
- decision: 意思決定の内容とその理由
- learning: 学び・教訓・得た知識
- error_log: ソフトウェア開発や技術作業中に発生したエラーとその解決の記録。日常生活の失敗は含めない（それは thought または event）
- idea: アイデア・企画・やってみたいこと
- reference: URL・記事・書籍などの参照情報
- event: 出来事・予定・起きたことの記録
- conversation: 会話・打ち合わせの記録

titleとtagsは必ず原文と同じ言語で書くこと（日本語の原文なら日本語）。

原文:
{$rawContent}
PROMPT,
        );
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{memory_type: string, title: string|null, tags: list<string>, importance: int}
     */
    private function normalize(array $decoded): array
    {
        $memoryType = (string) ($decoded['memory_type'] ?? 'thought');
        if (! in_array($memoryType, $this->registry->keys(), true)) {
            $memoryType = 'thought';
        }

        $title = isset($decoded['title']) && is_string($decoded['title']) && trim($decoded['title']) !== ''
            ? trim($decoded['title'])
            : null;

        $tags = is_array($decoded['tags'] ?? null)
            ? array_values(array_filter($decoded['tags'], fn ($tag) => is_string($tag) && $tag !== ''))
            : [];

        return [
            'memory_type' => $memoryType,
            'title' => $title,
            'tags' => $tags,
            'importance' => max(1, min(5, (int) ($decoded['importance'] ?? 3))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $text): array
    {
        $trimmed = trim($text);
        if (preg_match('/\{.*\}/s', $trimmed, $matches) === 1) {
            $trimmed = $matches[0];
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }
}
