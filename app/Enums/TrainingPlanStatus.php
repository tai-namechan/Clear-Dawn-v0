<?php

namespace App\Enums;

enum TrainingPlanStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Archived = 'archived';
}
