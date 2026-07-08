<?php

namespace App\Enums;

enum MetricValueType: string
{
    case Decimal = 'decimal';
    case Integer = 'integer';
    case Scale15 = 'scale_1_5';
}
