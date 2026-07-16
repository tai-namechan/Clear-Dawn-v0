<?php

namespace App\Enums;

enum GoalMetricDirection: string
{
    case Increase = 'increase';
    case Decrease = 'decrease';
    case Range = 'range';
}
