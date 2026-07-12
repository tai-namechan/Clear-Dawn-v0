<?php

namespace App\Domain\Kioku\Types;

final class ReferenceType extends MemoryTypeDefinition
{
    public function key(): string
    {
        return 'reference';
    }

    public function label(): string
    {
        return '資料';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => ['type' => 'string'],
                'site' => ['type' => 'string'],
                'saved_reason' => ['type' => 'string'],
            ],
        ];
    }

    public function extractionPrompt(string $rawContent): string
    {
        return <<<PROMPT
URL・資料メモを構造化。JSONのみ:
{"summary":"...","structured_data":{"url":"...","site":"...","saved_reason":"..."}}

原文:
{$rawContent}
PROMPT;
    }

    public function displayFields(): array
    {
        return [
            ['key' => 'url', 'label' => 'URL', 'type' => 'string'],
            ['key' => 'site', 'label' => 'サイト', 'type' => 'string'],
            ['key' => 'saved_reason', 'label' => '保存理由', 'type' => 'string'],
        ];
    }
}
