<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 複数の週次変数を持つ処方を、ラベル付きの形式へ移行する。
 *
 * 旧形式は intent にラベルだけ、note に内容だけをまとめて連結していたため、
 * 実行画面が note を ' / ' で分割すると2件目以降がラベルなしの行になり、
 * 1件目のラベルが両方の内容に付いて見えていた。
 *
 *   intent = '今週のCUE／音・身体'
 *   note   = '音を出す前に… / 開放弦。弓を置く…'
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
 *
 * 未開始プランのスナップショットも同じ文字列に限って置換する。実行済みの記録
 * （routine_session_steps）は書き換えない（ADR-0006）。
 */
return new class extends Migration
{
    public function up(): void
    {
        // 更新すると intent が検索条件から外れるため、OFFSET ページング（each）では
        // 後続チャンクで取りこぼす。id 基準の chunkById でページ位置をずらさない。
        DB::table('program_week_item_prescriptions')
            ->where('intent', 'like', '%／%')
            ->chunkById(100, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $this->relabel($row);
                }
            });
    }

    /**
     * 表示が壊れている旧形式へ戻す意味がないため、意図的に何もしない。
     */
    public function down(): void {}

    private function relabel(object $row): void
    {
        $labels = explode('／', (string) $row->intent);
        $notes = explode(' / ', (string) $row->note);

        // ラベルと内容が1対1で対応しないものは触らない
        if (count($labels) < 2 || count($labels) !== count($notes)) {
            return;
        }

        $relabeled = implode(' / ', array_map(
            static fn (string $label, string $text): string => $label.'：'.$text,
            $labels,
            $notes,
        ));

        DB::table('program_week_item_prescriptions')
            ->where('id', $row->id)
            ->update(['intent' => null, 'note' => $relabeled]);

        $this->normalizeUnstartedPlanSteps(
            $row->program_step_item_id,
            $row->intent.'：'.$row->note,
            $relabeled,
        );
    }

    /**
     * 移行前に生成済みで、まだ開始していないプランのスナップショットを揃える。
     *
     * プラン生成時に合成された文字列が分かっているので、その文字列に一致する箇所
     * だけを置換する（自由文の解析はしない）。セッションが1件でもあるプランは
     * 実行の記録なので対象外。
     */
    private function normalizeUnstartedPlanSteps(string $stepItemId, string $before, string $after): void
    {
        DB::table('routine_plan_steps')
            ->where('program_step_item_id', $stepItemId)
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('routine_sessions')
                ->whereColumn('routine_sessions.routine_plan_id', 'routine_plan_steps.routine_plan_id'))
            ->get(['id', 'note'])
            ->each(function (object $step) use ($before, $after): void {
                if (! str_contains((string) $step->note, $before)) {
                    return;
                }

                DB::table('routine_plan_steps')
                    ->where('id', $step->id)
                    ->update(['note' => str_replace($before, $after, (string) $step->note)]);
            });
    }
};
