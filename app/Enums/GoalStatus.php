<?php

namespace App\Enums;

enum GoalStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Achieved = 'achieved';
    case Abandoned = 'abandoned';
}
