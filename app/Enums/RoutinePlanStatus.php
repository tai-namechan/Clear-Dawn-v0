<?php

namespace App\Enums;

enum RoutinePlanStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Archived = 'archived';
}
