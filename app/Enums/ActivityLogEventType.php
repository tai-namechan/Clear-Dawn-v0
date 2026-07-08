<?php

namespace App\Enums;

enum ActivityLogEventType: string
{
    case MatrixItemCompleted = 'matrix_item_completed';
    case MatrixItemReopened = 'matrix_item_reopened';
    case TrainingRunCompleted = 'training_run_completed';
}
