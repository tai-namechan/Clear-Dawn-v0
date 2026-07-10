<?php

namespace App\Domain\Kioku\Types;

final class IdeaType extends MemoryTypeDefinition
{
    public function key(): string
    {
        return 'idea';
    }

    public function label(): string
    {
        return 'アイデア';
    }

    public function jsonSchema(): array
    {
        return [];
    }

    public function extractionPrompt(string $rawContent): string
    {
        return "アイデアを1〜2文で要約。JSON {\"summary\":\"...\"} のみ。\n\n".$rawContent;
    }

    public function displayFields(): array
    {
        return [];
    }
}
