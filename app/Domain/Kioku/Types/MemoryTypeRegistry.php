<?php

namespace App\Domain\Kioku\Types;

use InvalidArgumentException;

final class MemoryTypeRegistry
{
    /** @var array<string, MemoryTypeDefinition>|null */
    private static ?array $map = null;

    public function get(string $key): MemoryTypeDefinition
    {
        $map = $this->all();

        if (! isset($map[$key])) {
            throw new InvalidArgumentException("Unknown memory_type: {$key}");
        }

        return $map[$key];
    }

    public function tryGet(?string $key): ?MemoryTypeDefinition
    {
        if ($key === null) {
            return null;
        }

        return $this->all()[$key] ?? null;
    }

    /**
     * @return array<string, MemoryTypeDefinition>
     */
    public function all(): array
    {
        return self::$map ??= [
            'thought' => new ThoughtType,
            'emotion' => new EmotionType,
            'decision' => new DecisionType,
            'learning' => new LearningType,
            'error_log' => new ErrorLogType,
            'idea' => new IdeaType,
            'reference' => new ReferenceType,
            'event' => new EventType,
            'conversation' => new ConversationType,
        ];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }
}
