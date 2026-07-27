<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('videos:prune-pending')
    ->daily()
    ->withoutOverlapping(10)
    ->onOneServer();

// 滞留した文字起こしの自動復旧（監査 H-2）。
// 実装・テスト・ドキュメント（docs/product/kioku-quick-capture.md）は
// 揃っていたが、ここへの登録だけが漏れていた。未登録のままだと
// transcription_status が pending で詰まった記憶は永久に復帰せず、
// ユーザーには待機スピナーが出続ける。
Schedule::command('kioku:transcriptions:dispatch-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

// 定期キャッシュフローの生成（監査 H-2 / H-1）。
// 未登録だったため、生成が MoneyDashboardController の GET 副作用のみに
// 依存していた。「ダッシュボードを見たかどうか」で家計の予定が変わるのは
// 正しくないので、スケジューラを正とする。
Schedule::command('yoyu-money:generate-recurring')
    ->dailyAt('00:10')
    ->withoutOverlapping(30)
    ->onOneServer();

// AI 使用量台帳と正準合計のドリフト補正（監査 H-2）。
Schedule::command('ai:usage-reconcile')
    ->hourly()
    ->withoutOverlapping(15)
    ->onOneServer();

// テスト便りの掃除（監査 H-2）。
Schedule::command('kioku:letters:test:prune --expired-only')
    ->daily()
    ->withoutOverlapping(10)
    ->onOneServer();

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
