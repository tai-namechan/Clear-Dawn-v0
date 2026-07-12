<?php

namespace App\Domain\Yoyu\Data;

final readonly class MarginAnalysis
{
    public function __construct(
        public int $busyMinutes,
        public int $taskMinutes,
        public int $workingMinutes,
        public int $loadMinutes,
        public float $marginRatio,
        public int $marginScore,
        public string $marginLabel,
    ) {}

    /**
     * @return array{
     *     busy_minutes: int,
     *     task_minutes: int,
     *     working_minutes: int,
     *     load_minutes: int,
     *     margin_ratio: float,
     *     margin_score: int,
     *     margin_label: string
     * }
     */
    public function toArray(): array
    {
        return [
            'busy_minutes' => $this->busyMinutes,
            'task_minutes' => $this->taskMinutes,
            'working_minutes' => $this->workingMinutes,
            'load_minutes' => $this->loadMinutes,
            'margin_ratio' => $this->marginRatio,
            'margin_score' => $this->marginScore,
            'margin_label' => $this->marginLabel,
        ];
    }
}
