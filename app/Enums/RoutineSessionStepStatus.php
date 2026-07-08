<?php

namespace App\Enums;

enum RoutineSessionStepStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Skipped = 'skipped';
}
