<?php

namespace App\Services;

use App\Enums\DayAssignmentMode;
use App\Enums\DayPriorityTier;
use App\Enums\PhaseIntent;
use App\Enums\ProgramStatus;
use App\Enums\ProgramStepKind;
use App\Enums\ProgramVersionStatus;
use App\Enums\ProgressionMode;
use App\Enums\RequiredLevel;
use App\Enums\RoutineItemCategory;
use App\Enums\TrackingType;
use App\Models\PersonalProfileEntry;
use App\Models\Program;
use App\Models\ProgramDayStep;
use App\Models\ProgramDayTemplate;
use App\Models\ProgramStepItem;
use App\Models\ProgramVersion;
use App\Models\ProgramWeek;
use App\Models\RoutineItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 11週 統合プログラム（筋力・投球・栄養）を冪等に登録する。
 *
 * 個人実測値（1RM・体組成等）は一切含めない。メインリフトは基準リフト1RM比
 * （percent_of_reference）で登録し、表示重量は personal_profile_entries の
 * 1RM（import コマンドで投入）から導出する（ADR-0010）。
 *
 * 投球モジュールは H7 神経症状ロック前提で登録する（受診クリアまでビルド開始しない）。
 */
class InstallElevenWeekProgramService
{
    public const PROGRAM_NAME = '11週 統合プログラム（筋力・投球・栄養）';

    public function handle(User $user): Program
    {
        $existing = $user->programs()->where('name', self::PROGRAM_NAME)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user): Program {
            $items = $this->ensureRoutineItems($user);

            $program = $user->programs()->create([
                'name' => self::PROGRAM_NAME,
                'purpose' => '球速と送球能力の土台づくり。筋力→並進→捻転差→胸郭加速の順で伸ばす。肘は球数・距離のゲートで守る。',
                'design_philosophy' => 'プレップ→ムーブメント→パワー→（投球）→ストレングス→補助→アームケアの層構造。土日が2大ブロック、平日は短く。',
                'status' => ProgramStatus::Active,
            ]);

            $version = $program->versions()->create([
                'version_number' => 1,
                'status' => ProgramVersionStatus::Active,
                'starts_on' => '2026-07-16',
                'ends_on' => '2026-10-01',
                'approved_at' => now(),
            ]);

            $weeks = $this->createPhasesAndWeeks($version);
            $this->createDays($version, $items, $weeks);
            $this->createConstraints($version);

            return $program;
        });
    }

    /**
     * @return array<string, RoutineItem>
     */
    private function ensureRoutineItems(User $user): array
    {
        $definitions = [
            // メインリフト
            ['ベンチプレス', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['バックスクワット', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['デッドリフト', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['ベンチ・セカンダリ（パウズ/インクライン）', RoutineItemCategory::Strength, TrackingType::WeightReps],
            // 火曜
            ['KBアームバー', RoutineItemCategory::Mobility, TrackingType::WeightReps],
            ['ハーフニーリングチェストオープン', RoutineItemCategory::Mobility, TrackingType::Reps],
            ['インクラインDBプレス', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['チェストサポートロウ', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['フェイスプル', RoutineItemCategory::Care, TrackingType::WeightReps],
            ['サイドレイズ', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['カフ外旋（軽・バンド）', RoutineItemCategory::Care, TrackingType::Reps],
            ['トライセップス・ロープ', RoutineItemCategory::Strength, TrackingType::WeightReps],
            // 水曜
            ['WGS', RoutineItemCategory::Mobility, TrackingType::Reps],
            ['スタンディングラテラルリーチ', RoutineItemCategory::Mobility, TrackingType::Reps],
            ['スプリット70-70スロー', RoutineItemCategory::Baseball, TrackingType::Reps],
            ['シャドウピッチング', RoutineItemCategory::Baseball, TrackingType::Reps],
            ['懸垂 or ラットプルダウン', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['ワンハンドDBロウ', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['ランドマインプレス', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['リアデルトフライ', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['肩甲帯（前鋸筋・僧帽筋下部）', RoutineItemCategory::Care, TrackingType::Reps],
            ['ハンマーカール', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['ヨガ（常温）', RoutineItemCategory::Mobility, TrackingType::Duration],
            ['ロードワーク（平坦・短め）', RoutineItemCategory::Other, TrackingType::Distance],
            // 木曜
            ['ローデッドビースト', RoutineItemCategory::Mobility, TrackingType::Reps],
            ['1L RDL', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['ラットプルダウン', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['ヒップスラスト', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['ノルディック or レッグカール', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['デッドバグ', RoutineItemCategory::Mobility, TrackingType::Reps],
            // 金曜・月曜
            ['ピラティス', RoutineItemCategory::Mobility, TrackingType::Duration],
            ['クロストレーナー Zone2', RoutineItemCategory::Other, TrackingType::Duration],
            ['モビリティ（胸椎・股関節）', RoutineItemCategory::Mobility, TrackingType::Duration],
            // 土曜（投球）
            ['動的ウォームアップ（バンド肩モビリティ）', RoutineItemCategory::Mobility, TrackingType::Duration],
            ['キャッチボール（距離ランプ）', RoutineItemCategory::Baseball, TrackingType::Distance],
            ['ブルペン（隔週・据え置き）', RoutineItemCategory::Baseball, TrackingType::Count],
            ['サイドツイスト・スロー', RoutineItemCategory::Baseball, TrackingType::Reps],
            ['スプリット・ローテーショナルスロー', RoutineItemCategory::Baseball, TrackingType::Reps],
            ['ステップ＋回旋スロー', RoutineItemCategory::Baseball, TrackingType::Reps],
            ['MBスタッガードスラム', RoutineItemCategory::Baseball, TrackingType::Reps],
            ['sleeper / cross-body ストレッチ', RoutineItemCategory::Care, TrackingType::Duration],
            // 日曜（脚）
            ['インチワーム', RoutineItemCategory::Mobility, TrackingType::Reps],
            ['コサックスクワット＋ヒップローテーション', RoutineItemCategory::Mobility, TrackingType::Reps],
            ['オープンフロッグストレッチ', RoutineItemCategory::Mobility, TrackingType::Reps],
            ['パロフプレス・ローテーション', RoutineItemCategory::Care, TrackingType::Reps],
            ['MBスタッガードスクワットキャッチ', RoutineItemCategory::Baseball, TrackingType::Reps],
            ['ピッチングラテラルプッシュ', RoutineItemCategory::Baseball, TrackingType::Distance],
            ['バックステップスロー', RoutineItemCategory::Baseball, TrackingType::Reps],
            ['クロスオーバーグレーディングスロー', RoutineItemCategory::Baseball, TrackingType::Reps],
            ['カウンタームーブメントジャンプ', RoutineItemCategory::Strength, TrackingType::Reps],
            ['レッグプレス', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['ブルガリアンスクワット', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['ライイングレッグカール', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['カーフレイズ', RoutineItemCategory::Strength, TrackingType::WeightReps],
            ['プランク', RoutineItemCategory::Strength, TrackingType::Duration],
            ['サイドプランク', RoutineItemCategory::Strength, TrackingType::Duration],
        ];

        $items = [];

        foreach ($definitions as [$name, $category, $trackingType]) {
            $items[$name] = RoutineItem::query()->firstOrCreate(
                ['user_id' => $user->id, 'name' => $name],
                ['category' => $category, 'tracking_type' => $trackingType, 'is_active' => true],
            );
        }

        return $items;
    }

    /**
     * @return array<int, ProgramWeek> week_number => week
     */
    private function createPhasesAndWeeks(ProgramVersion $version): array
    {
        $phaseDefs = [
            ['基礎', PhaseIntent::Base, [1, 2, 3, 4], 'フォームと量を作る。5→4レップ・RPE7〜8。'],
            ['減量週', PhaseIntent::Deload, [5], '量を大きく減らして回復。関節をリセット。'],
            ['強化', PhaseIntent::Intensify, [6, 7, 8, 9], '3→2レップ。RPE8〜9で高重量に慣らす。'],
            ['調整', PhaseIntent::Taper, [10], '量を落として疲労を抜く。キレを戻す。'],
            ['測定', PhaseIntent::Test, [11], '新1RMに挑戦。フォーム最優先。'],
        ];

        $weekIntents = [
            1 => '基礎①', 2 => '基礎②', 3 => '基礎③', 4 => '基礎④',
            5 => '減量週', 6 => '強化①', 7 => '強化②', 8 => '強化③', 9 => '強化④',
            10 => '調整', 11 => '1RM測定',
        ];

        $weeks = [];
        $sort = 1;

        foreach ($phaseDefs as [$name, $intent, $weekNumbers, $conditions]) {
            $phase = $version->phases()->create([
                'name' => $name,
                'intent' => $intent,
                'sort_order' => $sort++,
                'progression_conditions' => $conditions,
            ]);

            foreach ($weekNumbers as $weekNumber) {
                $weeks[$weekNumber] = $version->weeks()->create([
                    'program_phase_id' => $phase->id,
                    'week_number' => $weekNumber,
                    'starts_on' => $version->starts_on->copy()->addWeeks($weekNumber - 1),
                    'intent' => $weekIntents[$weekNumber],
                ]);
            }
        }

        return $weeks;
    }

    /**
     * メインリフトの週次処方（percent = PDF 掲載重量 ÷ 基準1RM を4桁で固定）。
     *
     * @return array<string, array{percents: list<float>, schemes: list<array{int, int}>, rpes: list<float>}>
     *                                                                                                        schemes は [reps, sets]
     */
    private function weeklyMainTable(): array
    {
        return [
            PersonalProfileEntry::KEY_ONE_RM_BENCH => [
                'percents' => [0.7456, 0.7895, 0.8333, 0.8772, 0.7018, 0.9211, 0.9649, 1.0088, 1.0526, 0.9649],
                'schemes' => [[5, 4], [5, 4], [5, 3], [4, 4], [5, 2], [3, 4], [3, 3], [2, 4], [2, 3], [2, 2]],
                'rpes' => [7.0, 7.5, 8.0, 8.0, 6.0, 8.0, 8.5, 8.5, 9.0, 7.0],
            ],
            PersonalProfileEntry::KEY_ONE_RM_SQUAT => [
                'percents' => [0.7391, 0.7826, 0.8261, 0.8696, 0.6957, 0.9130, 0.9565, 1.0000, 1.0435, 0.9348],
                'schemes' => [[5, 4], [5, 4], [5, 3], [4, 4], [5, 2], [3, 4], [3, 3], [2, 4], [2, 3], [3, 2]],
                'rpes' => [7.0, 7.0, 7.5, 8.0, 6.0, 8.0, 8.0, 8.5, 9.0, 7.0],
            ],
            PersonalProfileEntry::KEY_ONE_RM_DEADLIFT => [
                'percents' => [0.8148, 0.8519, 0.8889, 0.9259, 0.7407, 0.9630, 1.0000, 1.0556, 1.0926, 0.9630],
                'schemes' => [[5, 3], [5, 3], [4, 3], [3, 3], [4, 2], [3, 2], [2, 3], [2, 2], [1, 3], [2, 2]],
                'rpes' => [7.0, 7.5, 8.0, 8.0, 6.0, 8.0, 8.0, 8.5, 8.5, 7.0],
            ],
        ];
    }

    /**
     * @param  array<string, RoutineItem>  $items
     * @param  array<int, ProgramWeek>  $weeks
     */
    private function createDays(ProgramVersion $version, array $items, array $weeks): void
    {
        $mainTable = $this->weeklyMainTable();

        // ---- DAY1 火曜: 胸・ベンチ + アームケア ----
        $day = $this->createDay($version, 'DAY1', '胸・ベンチ＋アームケア', 2, DayPriorityTier::Keep, 60, 70, 1);
        $step = $this->createStep($day, 'プレップ（準備・可動域）', ProgramStepKind::Preparation, 1, RequiredLevel::Recommended);
        $this->addItem($step, $items['KBアームバー'], 1, ['sets' => 2, 'reps' => 6, 'cues' => '肩の安定性']);
        $this->addItem($step, $items['ハーフニーリングチェストオープン'], 2, ['sets' => 2, 'reps' => 10, 'cues' => '胸椎回旋＝捻転差の材料']);
        $step = $this->createStep($day, 'メインセット', ProgramStepKind::Strength, 2, RequiredLevel::Required);
        $this->addMainLift($step, $items['ベンチプレス'], PersonalProfileEntry::KEY_ONE_RM_BENCH, $mainTable, $weeks);
        $step = $this->createStep($day, '補助種目', ProgramStepKind::Accessory, 3, RequiredLevel::Recommended, '重量は指定レップをRPE7-8で終えられる重さに調整（重量よりフォーム優先）');
        $this->addItem($step, $items['インクラインDBプレス'], 1, ['sets' => 3, 'reps' => 8, 'cues' => '肩に優しい角度（片手ずつDB）']);
        $this->addItem($step, $items['チェストサポートロウ'], 2, ['sets' => 3, 'reps' => 10, 'cues' => '腰を使わず引く']);
        $this->addItem($step, $items['サイドレイズ'], 3, ['sets' => 3, 'reps' => 12]);
        $this->addItem($step, $items['トライセップス・ロープ'], 4, ['sets' => 3, 'reps' => 12, 'cues' => '肘は伸ばしきりすぎない', 'required_level' => RequiredLevel::Skippable]);
        $step = $this->createStep($day, 'アームケア', ProgramStepKind::ArmCare, 4, RequiredLevel::Required);
        $this->addItem($step, $items['フェイスプル'], 1, ['sets' => 3, 'reps' => 15]);
        $this->addItem($step, $items['カフ外旋（軽・バンド）'], 2, ['sets' => 2, 'reps' => 15, 'cues' => '投球側は外旋筋が弱い']);

        // ---- DAY2 水曜: 選択日 ----
        $day = $this->createDay($version, 'DAY2', '選択日：上半身補助＋捻転差 / ヨガ / ロードワーク', 3, DayPriorityTier::CutOk, 40, 60, 2, isOptional: true);
        $group = $day->choiceGroup()->create([
            'name' => '水曜の選択',
            'selection_hint' => 'その週の状況で選ぶ（全部やらなくてよい）。時間がない週はまるごと削る。土曜の投球への疲労影響を考慮する。',
        ]);
        $optionA = $group->options()->create(['label' => '上半身補助＋捻転差', 'estimated_minutes' => 60, 'sort_order' => 1]);
        $optionB = $group->options()->create(['label' => 'ヨガ 60分', 'description' => '胸椎の回旋可動域＝捻転差の材料。腰のフォームも助ける', 'estimated_minutes' => 60, 'sort_order' => 2]);
        $optionC = $group->options()->create(['label' => 'ロードワーク 40分', 'description' => '走るのは週1・この日だけ（足底腱膜炎に配慮／脚の日から最も遠い）', 'estimated_minutes' => 40, 'sort_order' => 3]);
        $group->options()->create(['label' => '完全休養', 'estimated_minutes' => 0, 'sort_order' => 4]);

        $step = $this->createStep($day, 'プレップ', ProgramStepKind::Preparation, 1, RequiredLevel::Recommended, null, $optionA->id);
        $this->addItem($step, $items['WGS'], 1, ['sets' => 2, 'reps' => 6]);
        $this->addItem($step, $items['スタンディングラテラルリーチ'], 2, ['sets' => 2, 'reps' => 8, 'cues' => '胸郭を残す感覚']);
        $step = $this->createStep($day, 'ムーブメント（並進・捻転差）', ProgramStepKind::Movement, 2, RequiredLevel::Recommended, null, $optionA->id);
        $this->addItem($step, $items['スプリット70-70スロー'], 1, ['sets' => 2, 'reps' => 10, 'cues' => '下半身を固定して骨盤先行を体に入れる']);
        $this->addItem($step, $items['シャドウピッチング'], 2, ['sets' => 2, 'reps' => 10, 'cues' => '「接地するまで胸を我慢」だけ意識。投げない']);
        $step = $this->createStep($day, 'ベンチ・セカンダリ', ProgramStepKind::Strength, 3, RequiredLevel::Recommended, 'メイン重量の82%。週の進行はロードマップに連動。', $optionA->id);
        $benchSecondary = $this->addItem($step, $items['ベンチ・セカンダリ（パウズ/インクライン）'], 1, [
            'reference_lift' => PersonalProfileEntry::KEY_ONE_RM_BENCH,
            'progression_mode' => ProgressionMode::Weekly,
            'sets' => 3, 'reps' => 5, 'rpe_target' => 7.0,
        ]);
        $bench = $mainTable[PersonalProfileEntry::KEY_ONE_RM_BENCH];
        foreach ($weeks as $weekNumber => $week) {
            if ($weekNumber >= 11) {
                $week->itemPrescriptions()->create([
                    'program_step_item_id' => $benchSecondary->id,
                    'is_test' => true,
                    'note' => '測定週は補助を軽く。省略推奨。',
                ]);

                continue;
            }
            $week->itemPrescriptions()->create([
                'program_step_item_id' => $benchSecondary->id,
                'percent_of_reference' => round($bench['percents'][$weekNumber - 1] * 0.82, 4),
                'sets' => 3,
                'reps' => 5,
                'rpe_target' => 7.0,
                'intent' => 'セカンダリ（メインの82%）',
            ]);
        }
        $step = $this->createStep($day, '補助種目', ProgramStepKind::Accessory, 4, RequiredLevel::Skippable, null, $optionA->id);
        $this->addItem($step, $items['懸垂 or ラットプルダウン'], 1, ['sets' => 3, 'reps' => 8, 'cues' => '懸垂目標5回。プルダウン代替可']);
        $this->addItem($step, $items['ワンハンドDBロウ'], 2, ['sets' => 3, 'reps' => 10]);
        $this->addItem($step, $items['ランドマインプレス'], 3, ['sets' => 3, 'reps' => 10, 'cues' => '肩が不安な日の縦押し代替']);
        $this->addItem($step, $items['リアデルトフライ'], 4, ['sets' => 3, 'reps' => 15]);
        $this->addItem($step, $items['フェイスプル'], 5, ['sets' => 3, 'reps' => 20]);
        $this->addItem($step, $items['肩甲帯（前鋸筋・僧帽筋下部）'], 6, ['sets' => 2, 'reps' => 12]);
        $this->addItem($step, $items['ハンマーカール'], 7, ['sets' => 3, 'reps' => 12]);
        $step = $this->createStep($day, 'ヨガ', ProgramStepKind::Conditioning, 5, RequiredLevel::Recommended, null, $optionB->id);
        $this->addItem($step, $items['ヨガ（常温）'], 1, ['amount_value' => 60, 'amount_unit' => '分', 'cues' => 'タンニング直後は熱・汗系NG→常温クラスへ']);
        $step = $this->createStep($day, 'ロードワーク', ProgramStepKind::Conditioning, 6, RequiredLevel::Recommended, null, $optionC->id);
        $this->addItem($step, $items['ロードワーク（平坦・短め）'], 1, ['amount_value' => 40, 'amount_unit' => '分', 'cues' => 'Zone2（会話ができる強度）。土日は絶対に走らない']);

        // ---- DAY3 木曜: 背中・デッド + 後鎖 ----
        $day = $this->createDay($version, 'DAY3', '背中・デッド＋後鎖', 4, DayPriorityTier::Keep, 60, 70, 3);
        $step = $this->createStep($day, 'プレップ', ProgramStepKind::Preparation, 1, RequiredLevel::Recommended);
        $this->addItem($step, $items['ローデッドビースト'], 1, ['sets' => 2, 'reps' => 10]);
        $this->addItem($step, $items['1L RDL'], 2, ['sets' => 2, 'reps' => 5, 'cues' => '片手ずつDB']);
        $step = $this->createStep($day, 'メインセット', ProgramStepKind::Strength, 2, RequiredLevel::Required, '腰椎の負荷が最大の日。RPEが設計値を超えたら重量据え置き。下肢のしびれは即中止＋受診。');
        $this->addMainLift($step, $items['デッドリフト'], PersonalProfileEntry::KEY_ONE_RM_DEADLIFT, $mainTable, $weeks, abortCondition: '下肢のしびれ・腰の違和感が出たら即中止＋受診（S13）');
        $step = $this->createStep($day, '補助種目', ProgramStepKind::Accessory, 3, RequiredLevel::Recommended);
        $this->addItem($step, $items['ラットプルダウン'], 1, ['sets' => 3, 'reps' => 10]);
        $this->addItem($step, $items['チェストサポートロウ'], 2, ['sets' => 3, 'reps' => 12, 'cues' => '腰保護のため胸支持で']);
        $this->addItem($step, $items['ヒップスラスト'], 3, ['sets' => 3, 'reps' => 10, 'cues' => '臀部・腰に優しい後鎖']);
        $this->addItem($step, $items['ノルディック or レッグカール'], 4, ['sets' => 3, 'reps' => 10, 'cues' => 'ハム＝腰の保険。省略しない', 'required_level' => RequiredLevel::Required]);
        $this->addItem($step, $items['リアデルトフライ'], 5, ['sets' => 3, 'reps' => 15, 'required_level' => RequiredLevel::Skippable]);
        $this->addItem($step, $items['デッドバグ'], 6, ['sets' => 3, 'reps' => 12, 'cues' => '体幹の抗伸展・腰が浮かないよう']);

        // ---- DAY4 金曜: ピラティス + 軽い有酸素（筋トレなし） ----
        $day = $this->createDay($version, 'DAY4', 'ピラティス＋軽い有酸素（筋トレなし）', 5, DayPriorityTier::Keep, 55, 75, 4);
        $step = $this->createStep($day, 'コンディショニング', ProgramStepKind::Conditioning, 1, RequiredLevel::Recommended, 'この日を軽くするのが設計の要。土曜の投球に脚を残す。ここで頑張らない。');
        $this->addItem($step, $items['ピラティス'], 1, ['amount_value' => 55, 'amount_unit' => '分', 'cues' => '体幹・骨盤コントロール＝腰の保険（ヘルニア既往）']);
        $this->addItem($step, $items['クロストレーナー Zone2'], 2, ['amount_value' => 20, 'amount_unit' => '分', 'required_level' => RequiredLevel::Skippable, 'cues' => '任意。脚を追い込まない。走らない']);
        $this->addItem($step, $items['モビリティ（胸椎・股関節）'], 3, ['amount_value' => 10, 'amount_unit' => '分', 'required_level' => RequiredLevel::Skippable]);

        // ---- DAY5 土曜: 投球日フル + 回旋パワー ----
        $day = $this->createDay($version, 'DAY5', '投球日フル＋回旋パワー＋アームケア', 6, DayPriorityTier::NeverCut, 120, 150, 5,
            note: '週の主役。投球は距離で管理し「楽に感じるか」では判断しない。神経症状（H7）がある間はブルペンを増やさない。回旋MBは投球の後に置く。');
        $step = $this->createStep($day, 'プレップ（準備・可動域）', ProgramStepKind::Preparation, 1, RequiredLevel::Required);
        $this->addItem($step, $items['動的ウォームアップ（バンド肩モビリティ）'], 1, ['amount_value' => 10, 'amount_unit' => '分', 'cues' => '静的ストレッチをメインにしない']);
        $this->addItem($step, $items['ハーフニーリングチェストオープン'], 2, ['sets' => 2, 'reps' => 10]);
        $this->addItem($step, $items['スタンディングラテラルリーチ'], 3, ['sets' => 2, 'reps' => 8]);
        $step = $this->createStep($day, '投球（距離ベース）', ProgramStepKind::Throwing, 2, RequiredLevel::Required,
            'H7ロック中は投球ビルドを開始しない（現状の「2週に1回・無痛」を維持）。9-14m（30-45ft）各5球から開始→15ft/30ft刻みで漸進（無痛が通過条件）→上限37m（120ft）で打ち止め。全力遠投は禁止。');
        $this->addItem($step, $items['キャッチボール（距離ランプ）'], 1, [
            'amount_value' => 37, 'amount_unit' => 'm',
            'cues' => '開始9-14m・各5球。無痛が通過条件。120ftで肘負荷はマウンド投球と同水準に達する',
            'abort_condition' => '肘のじんじん・しびれ（尺骨神経症状）が出たら即中止・受診（H7）',
            'completion_condition' => '全段階を無痛で通過',
        ]);
        $this->addItem($step, $items['ブルペン（隔週・据え置き）'], 2, [
            'amount_value' => 50, 'amount_unit' => '球',
            'required_level' => RequiredLevel::Skippable,
            'cues' => '2週に1回・30-50球を維持。無痛の週だけごく少しずつ。球数×休養日ゲート適用（H1）',
            'abort_condition' => 'H1休養日未消化・前日投球（H2）・疲労下（H3）は投げない',
        ]);
        $this->addItem($step, $items['スプリット70-70スロー'], 3, ['sets' => 2, 'reps' => 10, 'required_level' => RequiredLevel::Skippable, 'cues' => '投球後の低強度フォーム反復']);
        $step = $this->createStep($day, 'パワー（回旋）', ProgramStepKind::Power, 3, RequiredLevel::Required, '回旋MB＝一人練習の本命。加重ボールを全力で投げる（禁忌）代わりにこれをやる。');
        $this->addItem($step, $items['サイドツイスト・スロー'], 1, ['sets' => 3, 'reps' => 6, 'fixed_load' => 2.5, 'load_unit' => 'kg', 'cues' => '「壁の一点を撃ち抜く」（外的フォーカス）']);
        $this->addItem($step, $items['スプリット・ローテーショナルスロー'], 2, ['sets' => 3, 'reps' => 6, 'fixed_load' => 2.5, 'load_unit' => 'kg', 'cues' => '「骨盤で先に壁を向く、胸は最後」']);
        $this->addItem($step, $items['ステップ＋回旋スロー'], 3, ['sets' => 3, 'reps' => 5, 'fixed_load' => 2.5, 'load_unit' => 'kg', 'cues' => '「横に長く移動してから撃つ」']);
        $this->addItem($step, $items['MBスタッガードスラム'], 4, ['sets' => 3, 'reps' => 5, 'fixed_load' => 2.5, 'load_unit' => 'kg', 'cues' => '全力・爆発的に']);
        $step = $this->createStep($day, 'アームケア', ProgramStepKind::ArmCare, 4, RequiredLevel::Required, '前腕強化は神経症状クリアまでロック（受診→クリア→解禁）。それまではフェイスプル/カフ外旋/肩甲帯/sleeperのみ。');
        $this->addItem($step, $items['フェイスプル'], 1, ['sets' => 3, 'reps' => 15]);
        $this->addItem($step, $items['カフ外旋（軽・バンド）'], 2, ['sets' => 2, 'reps' => 15]);
        $this->addItem($step, $items['肩甲帯（前鋸筋・僧帽筋下部）'], 3, ['sets' => 2, 'reps' => 12]);
        $this->addItem($step, $items['sleeper / cross-body ストレッチ'], 4, ['sets' => 2, 'amount_value' => 30, 'amount_unit' => '秒', 'cues' => '後方関節包']);

        // ---- DAY6 日曜: 脚・スクワット + 下半身 + 捻転差 ----
        $day = $this->createDay($version, 'DAY6', '脚・スクワット＋下半身＋捻転差', 7, DayPriorityTier::NeverCut, 100, 120, 6,
            note: '並進＝球速の土台を作る日。投球（土）の翌日に置く（逆にすると疲れた脚で投げる）。デッド（木）から3日空ける（腰椎の連日負荷回避）。');
        $step = $this->createStep($day, 'プレップ（準備・可動域）', ProgramStepKind::Preparation, 1, RequiredLevel::Recommended);
        $this->addItem($step, $items['インチワーム'], 1, ['sets' => 2, 'reps' => 6]);
        $this->addItem($step, $items['コサックスクワット＋ヒップローテーション'], 2, ['sets' => 2, 'reps' => 10, 'cues' => '股関節']);
        $this->addItem($step, $items['オープンフロッグストレッチ'], 3, ['sets' => 2, 'reps' => 10, 'cues' => '踏込脚を前に向けて接地するため']);
        $this->addItem($step, $items['パロフプレス・ローテーション'], 4, ['sets' => 2, 'reps' => 8, 'cues' => '骨盤を止めて胸郭だけ回す＝分離の学習']);
        $step = $this->createStep($day, 'ムーブメント（並進・捻転差）', ProgramStepKind::Movement, 2, RequiredLevel::Recommended, '3年来の課題（捻転差）に直接効かせる。');
        $this->addItem($step, $items['MBスタッガードスクワットキャッチ'], 1, ['sets' => 2, 'reps' => 10, 'fixed_load' => 2.0, 'load_unit' => 'kg', 'cues' => '並進']);
        $this->addItem($step, $items['ピッチングラテラルプッシュ'], 2, ['sets' => 2, 'amount_value' => 10, 'amount_unit' => 'm', 'cues' => '並進速度＝球速の土台']);
        $this->addItem($step, $items['バックステップスロー'], 3, ['sets' => 2, 'reps' => 10, 'cues' => '並進初期に軸脚股関節を早く伸ばさない・長く地面を押す']);
        $this->addItem($step, $items['クロスオーバーグレーディングスロー'], 4, ['sets' => 2, 'reps' => 10]);
        $step = $this->createStep($day, 'パワー', ProgramStepKind::Power, 3, RequiredLevel::Recommended);
        $this->addItem($step, $items['カウンタームーブメントジャンプ'], 1, ['sets' => 3, 'reps' => 5, 'cues' => '素早いしゃがみ→真上へ高く']);
        $step = $this->createStep($day, 'メインセット', ProgramStepKind::Strength, 4, RequiredLevel::Required, '過去に痛めた重量帯は低RPEで慎重に通過（腰椎ヘルニア既往）。');
        $this->addMainLift($step, $items['バックスクワット'], PersonalProfileEntry::KEY_ONE_RM_SQUAT, $mainTable, $weeks, abortCondition: '腰の違和感・下肢のしびれが出たら即中止＋受診（S13）');
        $step = $this->createStep($day, '補助種目', ProgramStepKind::Accessory, 5, RequiredLevel::Recommended);
        $this->addItem($step, $items['レッグプレス'], 1, ['sets' => 3, 'reps' => 10, 'cues' => '脊柱の負担を減らす主要補助']);
        $this->addItem($step, $items['ブルガリアンスクワット'], 2, ['sets' => 2, 'reps' => 10, 'cues' => '左右差の修正（片手ずつDB）']);
        $this->addItem($step, $items['ライイングレッグカール'], 3, ['sets' => 3, 'reps' => 12, 'cues' => 'ハム＝腰の保険。省略しない', 'required_level' => RequiredLevel::Required]);
        $this->addItem($step, $items['カーフレイズ'], 4, ['sets' => 4, 'reps' => 15, 'required_level' => RequiredLevel::Skippable]);
        $this->addItem($step, $items['プランク'], 5, ['sets' => 3, 'amount_value' => 40, 'amount_unit' => '秒', 'cues' => '腹圧の練習']);
        $this->addItem($step, $items['サイドプランク'], 6, ['sets' => 3, 'amount_value' => 30, 'amount_unit' => '秒']);

        // ---- DAY7 月曜: オフ / 回復有酸素 ----
        $day = $this->createDay($version, 'DAY7', 'オフ／回復有酸素', 1, DayPriorityTier::CutOk, 0, 30, 7, isOptional: true,
            note: '土日のがっつり2日の後の回復日。完全オフでも可。回復は設計の一部。');
        $step = $this->createStep($day, '回復有酸素', ProgramStepKind::Conditioning, 1, RequiredLevel::Skippable);
        $this->addItem($step, $items['クロストレーナー Zone2'], 1, ['amount_value' => 30, 'amount_unit' => '分', 'cues' => '低衝撃＝ヘルニア既往に最適。会話できる強度で']);
        $this->addItem($step, $items['モビリティ（胸椎・股関節）'], 2, ['amount_value' => 10, 'amount_unit' => '分', 'required_level' => RequiredLevel::Skippable]);
    }

    private function createDay(
        ProgramVersion $version,
        string $code,
        string $name,
        int $weekday,
        DayPriorityTier $tier,
        int $minMinutes,
        int $maxMinutes,
        int $sortOrder,
        bool $isOptional = false,
        ?string $note = null,
    ): ProgramDayTemplate {
        return $version->dayTemplates()->create([
            'code' => $code,
            'name' => $name,
            'priority_tier' => $tier,
            'assignment_mode' => DayAssignmentMode::WeekdayFixed,
            'fixed_weekday' => $weekday,
            'estimated_minutes_min' => $minMinutes,
            'estimated_minutes_max' => $maxMinutes,
            'is_optional' => $isOptional,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'note' => $note,
        ]);
    }

    private function createStep(
        ProgramDayTemplate $day,
        string $name,
        ProgramStepKind $kind,
        int $sortOrder,
        RequiredLevel $requiredLevel,
        ?string $note = null,
        ?string $choiceOptionId = null,
    ): ProgramDayStep {
        return $day->steps()->create([
            'program_choice_option_id' => $choiceOptionId,
            'name' => $name,
            'step_kind' => $kind,
            'sort_order' => $sortOrder,
            'required_level' => $requiredLevel,
            'note' => $note,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function addItem(ProgramDayStep $step, RoutineItem $item, int $sortOrder, array $attributes = []): ProgramStepItem
    {
        return $step->items()->create(array_merge([
            'routine_item_id' => $item->id,
            'sort_order' => $sortOrder,
            'required_level' => RequiredLevel::Recommended,
            'progression_mode' => ProgressionMode::Fixed,
        ], $attributes));
    }

    /**
     * メインリフト（週次処方つき）を登録する。
     *
     * @param  array<string, array{percents: list<float>, schemes: list<array{int, int}>, rpes: list<float>}>  $mainTable
     * @param  array<int, ProgramWeek>  $weeks
     */
    private function addMainLift(
        ProgramDayStep $step,
        RoutineItem $item,
        string $referenceLift,
        array $mainTable,
        array $weeks,
        ?string $abortCondition = null,
    ): void {
        $stepItem = $this->addItem($step, $item, 1, [
            'reference_lift' => $referenceLift,
            'progression_mode' => ProgressionMode::Weekly,
            'required_level' => RequiredLevel::Required,
            'load_unit' => 'kg',
            'abort_condition' => $abortCondition,
            'note' => '重量は現在1RM（personal_profile_entries）×週次比率から自動計算（1.25kg丸め）',
        ]);

        $table = $mainTable[$referenceLift];

        foreach ($weeks as $weekNumber => $week) {
            if ($weekNumber >= 11) {
                $week->itemPrescriptions()->create([
                    'program_step_item_id' => $stepItem->id,
                    'is_test' => true,
                    'intent' => '1RM測定',
                    'note' => 'アップ後、1回挑戦ごとに上げてPR挑戦。失敗orフォーム崩壊で終了。安全バー/補助必須。',
                ]);

                continue;
            }

            [$reps, $sets] = $table['schemes'][$weekNumber - 1];

            $week->itemPrescriptions()->create([
                'program_step_item_id' => $stepItem->id,
                'percent_of_reference' => $table['percents'][$weekNumber - 1],
                'sets' => $sets,
                'reps' => $reps,
                'rpe_target' => $table['rpes'][$weekNumber - 1],
                'intent' => $week->intent,
            ]);
        }
    }

    private function createConstraints(ProgramVersion $version): void
    {
        $constraints = [
            ['placement_rules', '配置原則（順番を入れ替えない）: 金曜に脚を使わない（土曜の投球に脚を残す）／脚（日）は投球（土）の後／ロードワークは水曜のみ・土日は走らない／投球は重いプレスから3日以上離す／スクワットとデッドは3日空ける（腰椎ヘルニア既往）／投球日の翌日は投げない。', null],
            ['min_execution_line', '最低実行ライン: 土・日・火・木の4日。水曜は余裕がある週だけ。', ['days' => ['DAY5', 'DAY6', 'DAY1', 'DAY3']]],
            ['cut_priority', '時間不足時の削減優先順位: ①絶対に削らない=土の距離ランプ/土の回旋MB/日のスクワットメイン ②次に守る=日の捻転差ドリル/ハムストリングス/アームケア/金のピラティス ③削ってよい=水曜まるごと/各日の補助後半/月・金の有酸素。', null],
            ['throwing_distance_cap', '投球距離の上限120ft（37m）。全力遠投は禁止（最大の肘・肩負荷）。', ['cap_ft' => 120, 'cap_m' => 37]],
            ['pitch_rest_table', 'ブルペン後の必要休養日（Pitch Smart 成人19-22歳表・保守側適用）: 0日≤30球/1日31-45/2日46-60/3日61-80/4日81-105/5日106+。1日上限120球。成人初心者は開始30-50球から漸増。', ['table' => [[30, 0], [45, 1], [60, 2], [80, 3], [105, 4], [null, 5]], 'daily_max' => 120, 'beginner_start' => [30, 50]]],
            ['no_consecutive_throwing', '連投禁止（H2）: 投球日の翌日は投球しない。', null],
            ['fatigued_throwing', '疲労下投球の回避（H3）: 睡眠6時間未満の継続 or 高疲労の日は投げない（疲労下の常習投球は肘肩手術リスク36倍）。', ['sleep_hours_threshold' => 6]],
            ['h7_neural_lock', 'H7 神経症状ロック: 肘のじんじん・しびれ（尺骨神経症状）はレッドフラグ。症状がある間は投球ビルドを開始しない・前腕強化も行わない。受診（尺骨神経評価/moving valgus/milking maneuver/受動的肩ROM実測）が入口。', null],
            ['aerobic_intensity_cap', '有酸素はすべてZone2（会話ができる強度）。高強度有酸素は筋力・回復・投球と干渉する。', null],
            ['no_weighted_ball', '加重ボール・プルダウン最大化・最大ロングトスは行わない（成人で効果未実証・傷害増加傾向。弱点=力を解決しない）。', null],
        ];

        foreach ($constraints as $index => [$key, $description, $params]) {
            $version->constraints()->create([
                'key' => $key,
                'kind' => 'program_rule',
                'description' => $description,
                'params' => $params,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
