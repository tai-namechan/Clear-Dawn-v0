<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 複数の週次変数を持つ処方を、ラベル付きの形式へ移行する。
 *
 * 旧形式は intent にラベルだけ、note に内容だけをまとめて連結していたため、
 * 実行画面が note を ' / ' で分割すると2件目以降がラベルなしの行になり、
 * 1件目のラベルが両方の内容に付いて見えていた。
 *
 *   intent = '今週のCUE／音・身体'
 *   note   = '音を出す前に…' / '開放弦。弓を置く…'
 *     → 今週のCUE／音・身体：音を出す前に…
 *       開放弦。弓を置く…                       ← ラベルなし
 *
 * 新形式は intent を使わず note の中でラベルと内容を対応させる。
 *
 *   intent = null
 *   note   = '今週のCUE：音を出す前に… / 音・身体：開放弦。弓を置く…'
 *
 * インストーラは同名プログラムがあれば何もしないため、既にインストール済みの
 * 環境はこの移行がないと旧形式のまま残る。ラベル区切り '／' は複数変数の処方
 * だけが使う文字なので、それを目印に対象を絞る（11週プログラムの intent は
 * 「基礎①」「1RM測定」等で '／' を含まない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('program_week_item_prescriptions')
            ->where('intent', 'like', '%／%')
            ->orderBy('id')
            ->each(function (object $row): void {
                $labels = explode('／', (string) $row->intent);
                $notes = explode(' / ', (string) $row->note);

                // ラベルと内容が1対1で対応しないものは触らない
                if (count($labels) < 2 || count($labels) !== count($notes)) {
                    return;
                }

                $pairs = array_map(
                    static fn (string $label, string $text): string => $label.'：'.$text,
                    $labels,
                    $notes,
                );

                DB::table('program_week_item_prescriptions')
                    ->where('id', $row->id)
                    ->update([
                        'intent' => null,
                        'note' => implode(' / ', $pairs),
                    ]);
            });
    }

    /**
     * 表示が壊れている旧形式へ戻す意味がないため、意図的に何もしない。
     */
    public function down(): void {}
};
