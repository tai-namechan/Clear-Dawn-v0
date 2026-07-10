<?php

namespace App\Domain\Kioku\Types;

final class LearningType extends MemoryTypeDefinition
{
    public function key(): string
    {
        return 'learning';
    }

    public function label(): string
    {
        return '学び';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topic' => ['type' => 'string'],
                'source' => ['type' => 'string'],
                'takeaway' => ['type' => 'string'],
            ],
        ];
    }

    public function extractionPrompt(string $rawContent): string
    {
        return <<<PROMPT
学びの記録を構造化。JSONのみ:
{"summary":"...","structured_data":{"topic":"...","source":"...","takeaway":"..."}}

原文:
{$rawContent}
PROMPT;
    }

    public function displayFields(): array
    {
        return [
            ['key' => 'topic', 'label' => 'トピック', 'type' => 'string'],
            ['key' => 'source', 'label' => '出典', 'type' => 'string'],
            ['key' => 'takeaway', 'label' => '学び', 'type' => 'string'],
        ];
    }
}
