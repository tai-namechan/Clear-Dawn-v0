<?php

namespace App\Enums;

/**
 * DAY 番号と曜日の割当方式（ADR-0012）。
 * weekday_fixed = 毎週同じ曜日 / sequential = 未実行 DAY の先頭から順に割当。
 */
enum DayAssignmentMode: string
{
    case WeekdayFixed = 'weekday_fixed';
    case Sequential = 'sequential';
}
