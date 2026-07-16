<?php

namespace App\Enums;

enum PhaseIntent: string
{
    case Base = 'base';
    case Deload = 'deload';
    case Intensify = 'intensify';
    case Taper = 'taper';
    case Test = 'test';
}
