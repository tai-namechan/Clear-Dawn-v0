<?php

namespace App\Enums;

enum RoutineSessionStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Aborted = 'aborted';
}
