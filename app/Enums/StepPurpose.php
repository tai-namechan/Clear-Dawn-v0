<?php

namespace App\Enums;

enum StepPurpose: string
{
    case Prep = 'prep';
    case Movement = 'movement';
    case Power = 'power';
    case Strength = 'strength';
    case Care = 'care';
    case Practice = 'practice';
    case Study = 'study';
    case Review = 'review';
    case Other = 'other';
}
