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

Schedule::command('calendar:sync-stale')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::command('kioku:letters:pilot:dispatch-due')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('yoyu-money:purge-imports')
    ->daily()
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('meals:prune-expired-lookups')
    ->daily()
    ->withoutOverlapping(10)
    ->onOneServer();
