<?php

namespace App\Domain\Kioku\Types;

final class EmotionType extends MemoryTypeDefinition
{
    public function key(): string
    {
        return 'emotion';
    }

    public function label(): string
    {
        return '感情';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'feeling' => ['type' => 'string'],
                'trigger' => ['type' => 'string'],
            ],
        ];
    }

    public function extractionPrompt(string $rawContent): string
    {
        return <<<PROMPT
感情の記録から summary と structured_data を抽出してください。
JSONのみ: {"summary":"...","structured_data":{"feeling":"...","trigger":"..."}}

原文:
{$rawContent}
PROMPT;
    }

    public function displayFields(): array
    {
        return [
            ['key' => 'feeling', 'label' => '感情', 'type' => 'string'],
            ['key' => 'trigger', 'label' => 'きっかけ', 'type' => 'string'],
        ];
    }
}
