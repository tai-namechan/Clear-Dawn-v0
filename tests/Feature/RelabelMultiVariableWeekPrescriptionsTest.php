<?php

namespace Tests\Feature;

use App\Models\ProgramWeekItemPrescription;
use App\Models\User;
use App\Services\GenerateProgramDayPlansService;
use App\Services\StartRoutineSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * インストーラは同名プログラムがあれば何もしないため、修正前にインストール済みの
 * 環境は移行しないと旧形式のまま残る。
 */
class RelabelMultiVariableWeekPrescriptionsTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require database_path(
            'migrations/2026_07_28_100002_relabel_multi_variable_week_prescriptions.php',
        );

        $migration->up();
    }

    public function test_it_pairs_labels_with_their_content_on_existing_prescriptions(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        $prescription = ProgramWeekItemPrescription::query()
            ->whereNull('intent')
            ->firstOrFail();

        // 修正前の形式へ戻す（ラベルと内容をそれぞれまとめて連結）
        DB::table('program_week_item_prescriptions')
            ->where('id', $prescription->id)
            ->update([
                'intent' => '今週のCUE／音・身体',
                'note' => '音を出す前に、出した後の響きまで想像する / 開放弦。弓を置く、肩を落とす、まっすぐ運ぶ',
            ]);

        $this->runMigration();

        $migrated = $prescription->fresh();

        $this->assertNull($migrated->intent);
        $this->assertSame(
            '今週のCUE：音を出す前に、出した後の響きまで想像する / 音・身体：開放弦。弓を置く、肩を落とす、まっすぐ運ぶ',
            $migrated->note,
        );

        // 分割したどの行もラベルを持つ
        foreach (explode(' / ', (string) $migrated->note) as $line) {
            $this->assertStringContainsString('：', $line);
        }
    }

    public function test_it_leaves_single_label_prescriptions_untouched(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        $single = ProgramWeekItemPrescription::query()
            ->where('intent', '音階')
            ->firstOrFail();

        $this->runMigration();

        $after = $single->fresh();

        $this->assertSame('音階', $after->intent);
        $this->assertSame($single->note, $after->note);
    }

    public function test_it_leaves_the_training_program_prescriptions_untouched(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();

        $before = ProgramWeekItemPrescription::query()
            ->orderBy('id')
            ->get()
            ->map(fn (ProgramWeekItemPrescription $row): array => [$row->intent, $row->note])
            ->all();

        $this->runMigration();

        $after = ProgramWeekItemPrescription::query()
            ->orderBy('id')
            ->get()
            ->map(fn (ProgramWeekItemPrescription $row): array => [$row->intent, $row->note])
            ->all();

        $this->assertSame($before, $after);
    }

    public function test_it_skips_rows_whose_labels_and_contents_do_not_line_up(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        $prescription = ProgramWeekItemPrescription::query()->whereNull('intent')->firstOrFail();

        // ラベル2つに対して内容1つ。壊れた形で書き換えない
        DB::table('program_week_item_prescriptions')
            ->where('id', $prescription->id)
            ->update(['intent' => 'ラベルA／ラベルB', 'note' => '内容がひとつだけ']);

        $this->runMigration();

        $after = $prescription->fresh();

        $this->assertSame('ラベルA／ラベルB', $after->intent);
        $this->assertSame('内容がひとつだけ', $after->note);
    }

    /**
     * 更新した行は検索条件（intent に '／' を含む）から外れるため、OFFSET ページング
     * だと2チャンク目以降で残りの行が前へ詰まり、読み飛ばしが起きる。
     * 移行のチャンクサイズ100を跨ぐ件数で、取りこぼしが無いことを確認する。
     */
    /**
     * 更新した行は検索条件（intent に '／' を含む）から外れる。OFFSET ページングだと
     * 1チャンク目を更新した時点で残りの行が前へ詰まり、次の OFFSET で読み飛ばす。
     *
     * `each()` の既定チャンクは1,000件なので、それを超える件数で取りこぼしを検出する。
     */
    public function test_it_migrates_every_matching_row_beyond_the_default_page_size(): void
    {
        foreach (range(1, 4) as $ignored) {
            $user = User::factory()->create();
            $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();
        }

        // 全処方を旧形式にして 1,000 件の境界を跨がせる
        DB::table('program_week_item_prescriptions')
            ->update(['intent' => '今週のCUE／音・身体', 'note' => '内容1 / 内容2']);

        $target = DB::table('program_week_item_prescriptions')
            ->where('intent', 'like', '%／%')
            ->count();

        $this->assertGreaterThan(1000, $target);

        $this->runMigration();

        $this->assertSame(0, DB::table('program_week_item_prescriptions')
            ->where('intent', 'like', '%／%')
            ->count());
    }

    public function test_it_normalizes_snapshots_of_plans_that_have_not_started(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        // 2026-07-30 = 木曜 = DAY B2（開放弦が複数変数を持つ）
        $plan = app(GenerateProgramDayPlansService::class)
            ->handle($user, Carbon::parse('2026-07-30'))
            ->firstOrFail();
        $step = $plan->steps->firstWhere('title', '開放弦');

        $prescription = ProgramWeekItemPrescription::query()
            ->where('program_step_item_id', $step->program_step_item_id)
            ->firstOrFail();

        // 移行前の状態（処方・プランとも旧形式）を作る
        DB::table('program_week_item_prescriptions')
            ->where('id', $prescription->id)
            ->update(['intent' => '今週のCUE／音・身体', 'note' => '内容1 / 内容2']);
        DB::table('routine_plan_steps')
            ->where('id', $step->id)
            ->update(['note' => '弓の直進と均一な音 / 今週のCUE／音・身体：内容1 / 内容2']);

        $this->runMigration();

        $this->assertSame(
            '弓の直進と均一な音 / 今週のCUE：内容1 / 音・身体：内容2',
            DB::table('routine_plan_steps')->where('id', $step->id)->value('note'),
        );
    }

    public function test_it_leaves_snapshots_of_started_plans_alone(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        $plan = app(GenerateProgramDayPlansService::class)
            ->handle($user, Carbon::parse('2026-07-30'))
            ->firstOrFail();
        $step = $plan->steps->firstWhere('title', '開放弦');

        $prescription = ProgramWeekItemPrescription::query()
            ->where('program_step_item_id', $step->program_step_item_id)
            ->firstOrFail();

        DB::table('program_week_item_prescriptions')
            ->where('id', $prescription->id)
            ->update(['intent' => '今週のCUE／音・身体', 'note' => '内容1 / 内容2']);

        $stale = '弓の直進と均一な音 / 今週のCUE／音・身体：内容1 / 内容2';
        DB::table('routine_plan_steps')->where('id', $step->id)->update(['note' => $stale]);

        // 開始済み = 実行の記録なので触らない（ADR-0006）
        app(StartRoutineSessionService::class)->handle($user, $plan->fresh());

        $this->runMigration();

        $this->assertSame(
            $stale,
            DB::table('routine_plan_steps')->where('id', $step->id)->value('note'),
        );
    }

    public function test_running_the_migration_twice_changes_nothing_further(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-violin-program', ['userId' => $user->id])->assertSuccessful();

        $prescription = ProgramWeekItemPrescription::query()->whereNull('intent')->firstOrFail();

        DB::table('program_week_item_prescriptions')
            ->where('id', $prescription->id)
            ->update([
                'intent' => '音階／週末テスト',
                'note' => 'G長調 1oct / 30秒動画：開放弦＋G音階＋カノン冒頭',
            ]);

        $this->runMigration();
        $once = $prescription->fresh()->note;

        $this->runMigration();

        $this->assertSame($once, $prescription->fresh()->note);
        $this->assertSame(
            '音階：G長調 1oct / 週末テスト：30秒動画：開放弦＋G音階＋カノン冒頭',
            $once,
        );
    }
}
