<?php

namespace App\Domain\Kioku\Types;

final class ConversationType extends MemoryTypeDefinition
{
    public function key(): string
    {
        return 'conversation';
    }

    public function label(): string
    {
        return '相談';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topic' => ['type' => 'string'],
                'conclusion' => ['type' => 'string'],
            ],
        ];
    }

    public function extractionPrompt(string $rawContent): string
    {
        return <<<PROMPT
相談の要約を構造化。JSONのみ:
{"summary":"...","structured_data":{"topic":"...","conclusion":"..."}}

原文:
{$rawContent}
PROMPT;
    }

    public function displayFields(): array
    {
        return [
            ['key' => 'topic', 'label' => 'トピック', 'type' => 'string'],
            ['key' => 'conclusion', 'label' => '結論', 'type' => 'string'],
        ];
    }
}
