<?php

namespace App\Domain\Yoyu\Data;

/**
 * Deterministic "今日の一手" from Clear Dawn matrix (MatrixRowKey::Current).
 */
final readonly class ClearDawnHand
{
    public function __construct(
        public string $id,
        public string $title,
        public string $lifeAreaName,
        public string $lifeAreaId,
        public int $sortOrder,
    ) {}

    /**
     * Legacy Yoyu Today prop shape until PR-D2 structured UI.
     *
     * @return array{id: string, goal: string, action: string, estimate: int, life_area: string}
     */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'goal' => $this->lifeAreaName,
            'action' => $this->title,
            'estimate' => 30,
            'life_area' => $this->lifeAreaName,
        ];
    }
}
