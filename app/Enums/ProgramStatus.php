<?php

namespace App\Enums;

enum ProgramStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
    case Aborted = 'aborted';
}
