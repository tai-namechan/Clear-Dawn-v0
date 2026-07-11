<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('videos:prune-pending')->daily();

Schedule::command('ai:usage-reap')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();
