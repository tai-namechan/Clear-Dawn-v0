<?php

namespace App\Enums;

enum TrackingType: string
{
    case WeightReps = 'weight_reps';
    case Reps = 'reps';
    case Duration = 'duration';
    case Distance = 'distance';
    case Check = 'check';
}
