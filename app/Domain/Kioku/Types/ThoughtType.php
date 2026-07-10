<?php

namespace App\Domain\Kioku\Types;

final class ThoughtType extends MemoryTypeDefinition
{
    public function key(): string
    {
        return 'thought';
    }

    public function label(): string
    {
        return '思考';
    }

    public function jsonSchema(): array
    {
        return [];
    }

    public function extractionPrompt(string $rawContent): string
    {
        return "次の思考メモを1〜2文で要約し、JSON {\"summary\":\"...\"} のみ返してください。\n\n".$rawContent;
    }

    public function displayFields(): array
    {
        return [];
    }
}
