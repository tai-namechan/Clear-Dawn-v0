<?php

namespace App\Domain\Kioku;

enum KiokuLetterMode: string
{
    case Live = 'live';
    case Test = 'test';
}
