<?php

namespace App\Enums;

enum TrainingRunStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Aborted = 'aborted';
}
