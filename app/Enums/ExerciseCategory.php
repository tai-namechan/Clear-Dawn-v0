<?php

namespace App\Enums;

enum ExerciseCategory: string
{
    case Strength = 'strength';
    case Baseball = 'baseball';
    case Mobility = 'mobility';
    case Care = 'care';
    case Music = 'music';
    case Other = 'other';
}
