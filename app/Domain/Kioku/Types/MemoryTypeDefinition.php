<?php

namespace App\Domain\Kioku\Types;

abstract class MemoryTypeDefinition
{
    abstract public function key(): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function jsonSchema(): array;

    abstract public function extractionPrompt(string $rawContent): string;

    /**
     * @return list<array{key: string, label: string, type: string}>
     */
    abstract public function displayFields(): array;

    public function label(): string
    {
        return $this->key();
    }
}
