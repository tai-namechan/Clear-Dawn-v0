<?php

namespace App\Domain\Kioku\Types;

final class ErrorLogType extends MemoryTypeDefinition
{
    public function key(): string
    {
        return 'error_log';
    }

    public function label(): string
    {
        return 'エラー';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['error_message'],
            'properties' => [
                'error_message' => ['type' => 'string'],
                'environment' => ['type' => 'string'],
                'cause' => ['type' => 'string'],
                'solution' => ['type' => 'array', 'items' => ['type' => 'string']],
                'related_files' => ['type' => 'array', 'items' => ['type' => 'string']],
                'resolved' => ['type' => 'boolean'],
            ],
        ];
    }

    public function extractionPrompt(string $rawContent): string
    {
        return <<<PROMPT
エラーと解決の記録を構造化。JSONのみ:
{"summary":"...","structured_data":{"error_message":"...","environment":"...","cause":"...","solution":[],"related_files":[],"resolved":false}}

原文:
{$rawContent}
PROMPT;
    }

    public function displayFields(): array
    {
        return [
            ['key' => 'error_message', 'label' => 'エラー文', 'type' => 'string'],
            ['key' => 'environment', 'label' => '環境', 'type' => 'string'],
            ['key' => 'cause', 'label' => '原因', 'type' => 'string'],
            ['key' => 'solution', 'label' => '解決手順', 'type' => 'list'],
            ['key' => 'related_files', 'label' => '関連ファイル', 'type' => 'list'],
            ['key' => 'resolved', 'label' => '解決済み', 'type' => 'boolean'],
        ];
    }
}
