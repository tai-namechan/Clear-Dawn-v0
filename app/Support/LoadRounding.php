<?php

namespace App\Support;

class LoadRounding
{
    /**
     * バーベル重量を 1.25kg 単位に丸める（strength_program.html の r125 と同一挙動）。
     */
    public static function r125(float $weight): float
    {
        return round($weight / 1.25) * 1.25;
    }
}
