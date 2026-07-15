<?php

namespace App\Domain\Kioku;

enum KiokuConciergeScheduleState: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Paused = 'paused';
    case Halted = 'halted';
    case Completed = 'completed';

    public function canGenerate(): bool
    {
        return $this === self::Active;
    }
}
