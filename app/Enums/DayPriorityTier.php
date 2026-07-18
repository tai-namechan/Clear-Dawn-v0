<?php

namespace App\Enums;

/**
 * 時間不足時の削減優先順位（PDF §1）。
 * never_cut = 絶対に削らない / keep = なるべく残す / cut_ok = 時間不足時に削ってよい。
 */
enum DayPriorityTier: string
{
    case NeverCut = 'never_cut';
    case Keep = 'keep';
    case CutOk = 'cut_ok';
}
