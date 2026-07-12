<?php

namespace App\Domain\Yoyu\Support;

/**
 * Shared constants for travel lead time and working-day analysis.
 * Keep in sync with resources/js/lib/yoyuCalc.ts (PREP_MIN / BUFFER_MIN).
 */
final class YoyuTravelConstants
{
    public const PREP_MINUTES = 10;

    public const BUFFER_MINUTES = 5;

    public const WORKING_START_HOUR = 7;

    public const WORKING_END_HOUR = 23;

    /** 07:00–23:00 inclusive span in minutes. */
    public const WORKING_MINUTES = 16 * 60;

    /** Daily task estimate cap for the margin meter. */
    public const TASK_MINUTES_CAP = 240;

    public const MIN_GAP_MINUTES = 30;

    public const MAX_SUGGESTIBLE_GAPS = 5;
}
