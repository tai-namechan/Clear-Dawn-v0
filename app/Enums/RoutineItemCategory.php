<?php

namespace App\Enums;

enum RoutineItemCategory: string
{
    case Strength = 'strength';
    case Baseball = 'baseball';
    case Mobility = 'mobility';
    case Care = 'care';
    case Music = 'music';
    case Study = 'study';
    case Life = 'life';
    case Other = 'other';
}
