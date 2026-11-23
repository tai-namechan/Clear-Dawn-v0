<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Yoyu Money CSV import storage
    |--------------------------------------------------------------------------
    |
    | 取り込み対象の CSV 原本を置くディスク。
    |
    | Web コンテナがアップロードを書き込み、キューコンテナ
    | （ProcessMoneyImportJob）が読み出す。Laravel Cloud では両者が
    | ファイルシステムを共有しないため、本番では必ず Object Storage の
    | ディスク名を指定すること。local のままだと取り込みが必ず失敗する
    | （docs/audit/2026-07-26-pre-release-audit.md C-3）。
    |
    | 同じ理由で config/meals.php の label_ocr.disk も外出ししてある。
    |
    | ローカル開発とテストは local のままでよい。
    |
    */

    'money' => [
        'import' => [
            'disk' => env('YOYU_MONEY_IMPORT_DISK', 'local'),
        ],
    ],

];
