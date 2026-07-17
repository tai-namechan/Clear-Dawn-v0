<?php

namespace App\Enums;

enum ProgramVersionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Superseded = 'superseded';
}
