<?php

namespace App\Domain\Kioku\Types;

final class DecisionType extends MemoryTypeDefinition
{
    public function key(): string
    {
        return 'decision';
    }

    public function label(): string
    {
        return '判断';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['situation', 'decision', 'reason'],
            'properties' => [
                'situation' => ['type' => 'string'],
                'constraints' => ['type' => 'array', 'items' => ['type' => 'string']],
                'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                'decision' => ['type' => 'string'],
                'reason' => ['type' => 'string'],
                'review_condition' => ['type' => 'string'],
            ],
        ];
    }

    public function extractionPrompt(string $rawContent): string
    {
        return <<<PROMPT
判断の記録から構造化してください。JSONのみ返してください。
{"summary":"...","structured_data":{"situation":"...","constraints":[],"options":[],"decision":"...","reason":"...","review_condition":"いつ・何が起きたら見直すか"}}

原文:
{$rawContent}
PROMPT;
    }

    public function displayFields(): array
    {
        return [
            ['key' => 'situation', 'label' => '状況', 'type' => 'string'],
            ['key' => 'constraints', 'label' => '制約', 'type' => 'list'],
            ['key' => 'options', 'label' => '選択肢', 'type' => 'list'],
            ['key' => 'decision', 'label' => '判断', 'type' => 'string'],
            ['key' => 'reason', 'label' => '理由', 'type' => 'string'],
            ['key' => 'review_condition', 'label' => '見直し条件', 'type' => 'string'],
        ];
    }
}
