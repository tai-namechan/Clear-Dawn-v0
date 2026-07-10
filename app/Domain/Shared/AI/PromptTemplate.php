<?php

namespace App\Domain\Shared\AI;

final class PromptTemplate
{
    public function __construct(
        public readonly string $version,
        public readonly string $fixedPrefix,
        public readonly string $variableSuffix,
    ) {}

    public function render(): string
    {
        return trim($this->fixedPrefix)."\n\n".trim($this->variableSuffix);
    }

    public static function make(string $version, string $fixedPrefix, string $variableSuffix): self
    {
        return new self($version, $fixedPrefix, $variableSuffix);
    }
}
