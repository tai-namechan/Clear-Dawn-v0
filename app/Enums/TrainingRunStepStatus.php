<?php

namespace App\Enums;

enum TrainingRunStepStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Skipped = 'skipped';
}
