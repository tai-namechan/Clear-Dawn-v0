<?php

namespace App\Enums;

enum StepPurpose: string
{
    case Prep = 'prep';
    case Movement = 'movement';
    case Power = 'power';
    case Strength = 'strength';
    case Care = 'care';
    case Skill = 'skill';
    case Other = 'other';
}
