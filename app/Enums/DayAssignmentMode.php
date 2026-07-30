<?php

namespace App\Enums;

/**
 * DAY 番号と曜日の割当方式（ADR-0012）。
 *
 * - weekday_fixed = 毎週同じ曜日
 * - sequential = 未割当 DAY の先頭から順に割当（版の中で1回だけ消費される）
 * - fallback = 自動生成では選ばれない。予定を実行できない日に手動で差し替えるための
 *   短縮版 DAY。sequential と違い消費されないので、何度でも使える。
 */
enum DayAssignmentMode: string
{
    case WeekdayFixed = 'weekday_fixed';
    case Sequential = 'sequential';
    case Fallback = 'fallback';
}
