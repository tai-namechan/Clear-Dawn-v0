<?php

namespace App\Enums;

enum RequiredLevel: string
{
    case Required = 'required';
    case Recommended = 'recommended';
    case Skippable = 'skippable';
}
