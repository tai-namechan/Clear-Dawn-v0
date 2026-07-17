<?php

namespace App\Enums;

/**
 * 処方値の決まり方。
 * fixed = 固定値 / weekly = program_week_item_prescriptions の週別表 / percent = 基準リフト1RM比。
 */
enum ProgressionMode: string
{
    case Fixed = 'fixed';
    case Weekly = 'weekly';
    case Percent = 'percent';
}
