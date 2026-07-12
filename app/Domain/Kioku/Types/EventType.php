<?php

namespace App\Domain\Kioku\Types;

final class EventType extends MemoryTypeDefinition
{
    public function key(): string
    {
        return 'event';
    }

    public function label(): string
    {
        return '出来事';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'origin' => ['type' => 'string'],
                'kind' => ['type' => 'string'],
                'outcome' => ['type' => 'string'],
            ],
        ];
    }

    public function extractionPrompt(string $rawContent): string
    {
        return <<<PROMPT
出来事を構造化。JSONのみ:
{"summary":"...","structured_data":{"origin":"...","kind":"...","outcome":"..."}}

原文:
{$rawContent}
PROMPT;
    }

    public function displayFields(): array
    {
        return [
            ['key' => 'origin', 'label' => '発生元', 'type' => 'string'],
            ['key' => 'kind', 'label' => '種別', 'type' => 'string'],
            ['key' => 'outcome', 'label' => '結果', 'type' => 'string'],
        ];
    }
}
