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
 * ヴァイオリン復帰プログラム（10週・2026-07-27 〜 10-01）を冪等に登録する。
 *
 * 週ごとに変わる指示（音階の調と♩、カノンの到達小節、今週のCUE、進級条件など）は
 * program_week_item_prescriptions に載せる。メインリフトの重量処方と同じ仕組みで、
 * プラン生成時にその日のルーティンステップへスナップショットされる（ADR-0012）。
 *
 * 先生の言葉（原文保存層）・参考URL・個人が特定できる情報は登録しない。
 */
class InstallViolinProgramService
{
    public const PROGRAM_NAME = 'ヴァイオリン復帰プログラム（10週）';

    private const STARTS_ON = '2026-07-27';

    private const ENDS_ON = '2026-10-01';

    /**
     * 週別処方を載せるステップ種目。タグ => ProgramStepItem のリスト。
     *
     * @var array<string, list<ProgramStepItem>>
     */
    private array $tagged = [];

    public function handle(User $user): Program
    {
        $existing = $user->programs()->where('name', self::PROGRAM_NAME)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user): Program {
            $this->tagged = [];

            $items = $this->ensureRoutineItems($user);

            $program = $user->programs()->create([
                'name' => self::PROGRAM_NAME,
                'purpose' => '10/1のレッスン復帰までに、音・弓・譜読みを作り直す。合格は完璧な演奏ではなく、先生が次を設計できる状態。',
                'design_philosophy' => '頭は先を描く／身体は先に準備する／意識は目の前に置く／音楽は堂々と届ける。'
                    .'保存する層（教わった言葉）と今日使う層（1点だけのCUE）を分ける。上げるのは原則1軸。',
                'status' => ProgramStatus::Active,
            ]);

            $version = $program->versions()->create([
                'version_number' => 1,
                'status' => ProgramVersionStatus::Active,
                'starts_on' => self::STARTS_ON,
                'ends_on' => self::ENDS_ON,
                'approved_at' => now(),
            ]);

            $weeks = $this->createPhasesAndWeeks($version);
            $this->createDays($version, $items);
            $this->createWeekPrescriptions($weeks);
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
            ['身体＋調弦', TrackingType::Duration, '分'],
            ['身体（呼吸・肩甲帯・前腕）', TrackingType::Duration, '分'],
            ['開放弦', TrackingType::Duration, '分'],
            ['音階（小野アンナ）', TrackingType::Duration, '分'],
            ['音階テスト', TrackingType::Duration, '分'],
            ['譜読み', TrackingType::Duration, '分'],
            ['音名フラッシュ', TrackingType::Duration, '分'],
            ['リズム（手拍子・開放弦）', TrackingType::Duration, '分'],
            ['先読み（30秒スキャン→初見）', TrackingType::Duration, '分'],
            ['初見テスト', TrackingType::Duration, '分'],
            ['譜面を見て歌う', TrackingType::Duration, '分'],
            ['4音セル', TrackingType::Duration, '分'],
            ['エラー1箇所', TrackingType::Duration, '分'],
            ['止まらない1回', TrackingType::Duration, '分'],
            ['カノン', TrackingType::Duration, '分'],
            ['篠崎（短曲）', TrackingType::Duration, '分'],
            ['既習曲（週替わり復元）', TrackingType::Duration, '分'],
            ['好きな曲', TrackingType::Duration, '分'],
            ['通し／録音', TrackingType::Duration, '分'],
            ['録音＋自己評価', TrackingType::Duration, '分'],
            ['動画観察（音だけ）', TrackingType::Duration, '分'],
            ['動画観察（動きだけ）', TrackingType::Duration, '分'],
            ['動画観察（音＋動き）', TrackingType::Duration, '分'],
            ['軽奏（無症状時のみ）', TrackingType::Duration, '分'],
            ['練習記録', TrackingType::Text, null],
            ['翌週計画（Green／Yellow／Red）', TrackingType::Text, null],
        ];

        $items = [];

        foreach ($definitions as [$name, $trackingType, $amountUnit]) {
            $items[$name] = RoutineItem::query()->firstOrCreate(
                ['user_id' => $user->id, 'name' => $name],
                [
                    'category' => $name === '身体＋調弦' || $name === '身体（呼吸・肩甲帯・前腕）'
                        ? RoutineItemCategory::Mobility
                        : RoutineItemCategory::Music,
                    'tracking_type' => $trackingType,
                    'default_amount_unit' => $amountUnit,
                    'is_active' => true,
                ],
            );
        }

        return $items;
    }

    /**
     * 週ごとに変わる処方の一次データ（資料の10週ロードマップ）。
     *
     * @return array<int, array{
     *     phase: string, theme: string, span: string, play: string, split: string,
     *     tone: string, read: string, book: string, test: string, gate: string,
     *     scale: string, bpm: int, canon: string, cue: string
     * }>
     */
    private function weeklyTable(): array
    {
        return [
            1 => [
                'phase' => '再接触', 'theme' => '楽器と身体の再会', 'span' => '7/27–8/2',
                'play' => '20→30分', 'split' => '10分＋休み＋10分',
                'tone' => '開放弦。弓を置く、肩を落とす、まっすぐ運ぶ',
                'read' => '譜表の基準音：開放弦G/D/A/Eと中央C付近。20音を声に出す',
                'book' => 'カノン冒頭8〜16小節を超低速。Happy Birthdayで成功体験',
                'test' => '30秒動画：開放弦＋G音階＋カノン冒頭。良否を判断せず保存',
                'gate' => '実奏中・当日夜・翌朝に痛み／しびれなし',
                'scale' => 'G長調 1oct', 'bpm' => 50, 'canon' => '1–16小節',
                'cue' => '音を出す前に、出した後の響きまで想像する',
            ],
            2 => [
                'phase' => '音の均一', 'theme' => '音の均一と弓速', 'span' => '8/3–8/9',
                'play' => '30〜35分', 'split' => '15〜20分×2',
                'tone' => '全弓4拍。弓元／中央／先で音量を揃える。弓速を一定に保つ',
                'read' => '4分音符・2分音符・8分音符を手拍子→開放弦',
                'book' => '小野アンナ：G/D/A長調のうち最も楽な1調。カノンを区切る',
                'test' => '同じ30秒を再録。W1より良い点を1つだけ書く',
                'gate' => '肩が上がらず、音が崩れる前に止められる',
                'scale' => 'G長調 1oct', 'bpm' => 54, 'canon' => '1–24小節',
                'cue' => '弓を返した直後も速度を変えず、音の線を切らない',
            ],
            3 => [
                'phase' => '左手', 'theme' => '左手フレームと返し', 'span' => '8/10–8/16',
                'play' => '35〜40分', 'split' => '20分＋3〜5分休＋15分',
                'tone' => '左手フレーム。指を押し込みすぎず、置いて離す。弓の返しを振り子で',
                'read' => '1拍先マーカー。篠崎の既習または易しい8小節を止まらず読む',
                'book' => '篠崎2巻：『驚愕』または『アマリリス』から短い1曲を選択',
                'test' => '音名20問：18/20を3秒以内。新曲8小節の連続演奏',
                'gate' => '2日連続で翌朝症状なし',
                'scale' => 'D長調 1oct', 'bpm' => 56, 'canon' => '1–32小節',
                'cue' => '返しの直前で速めない。緩めてから折り返す',
            ],
            4 => [
                'phase' => '移弦', 'theme' => '移弦の準備と先読み', 'span' => '8/17–8/23',
                'play' => '40〜45分', 'split' => '20分＋5分休＋20分',
                'tone' => '弦移動。肘の高さを先に準備し、弓を暴れさせない',
                'read' => '演奏前30秒スキャン：調号・拍子・臨時記号・最難所・終点',
                'book' => 'G/D/A長調をローテーション。カノンを前半／後半へ分割',
                'test' => 'カノン半分を止まらず録音。ミスの場所を3つ以内に特定',
                'gate' => '基礎後も集中が残る',
                'scale' => 'A長調 1oct', 'bpm' => 58, 'canon' => '1–43小節',
                'cue' => '音が変わる前に、肘だけ先に次の弦へ向かわせる',
            ],
            5 => [
                'phase' => '接続', 'theme' => '音階から曲へ接続', 'span' => '8/24–8/30',
                'play' => '45〜50分', 'split' => '22分＋5分休＋23分',
                'tone' => '音程：弾く前に歌う→置く→弾く→響きを聴く',
                'read' => '初見1回目は止まらない。2回目だけ修正する',
                'book' => '篠崎短曲を1曲完了。カノンは原速の60〜70%で連結',
                'test' => '篠崎1曲を通し、録音から『次の1点』を決める',
                'gate' => '録音で終盤の姿勢・音が落ちすぎない',
                'scale' => 'G長調 2oct', 'bpm' => 60, 'canon' => '43–60小節',
                'cue' => '今の音を弾きながら、2小節先の到着点を思い描く',
            ],
            6 => [
                'phase' => 'フレーズ', 'theme' => '弓の配分とフレーズ', 'span' => '8/31–9/6',
                'play' => '45〜55分', 'split' => '25分＋5分休＋25分',
                'tone' => '弓の配分。フレーズ終点まで逆算する。長い音の前に弓位置を作る',
                'read' => '2小節単位で形を見る。音名ではなく上行／下行／反復を言う',
                'book' => 'カノン70〜80%。愛のあいさつを週替わり復元曲へ',
                'test' => '60〜90秒録画。姿勢・音・リズムを各5点で自己評価',
                'gate' => '痛み0、張り2/10以下',
                'scale' => 'G長調 2oct', 'bpm' => 63, 'canon' => '全体7割',
                'cue' => '長い音の前に、弓の位置を先に作っておく',
            ],
            7 => [
                'phase' => '再現性', 'theme' => '再現性', 'span' => '9/7–9/13',
                'play' => '50〜60分', 'split' => '25分＋5分休＋25分',
                'tone' => '強弱を弓速中心で作り、押さえつけない',
                'read' => '1拍先を維持して8〜16小節。間違っても拍を止めない',
                'book' => '篠崎2曲目または1曲目を深掘り。カノン80〜90%',
                'test' => 'カノン全体の初回通し。止まった場所だけ翌週の課題へ',
                'gate' => '3回中2回の再現。翌朝に残らない',
                'scale' => 'G/D/A 日替わり', 'bpm' => 66, 'canon' => '全体通し',
                'cue' => '遅くなってもいい。止まらず丁寧に進む',
            ],
            8 => [
                'phase' => '模擬1', 'theme' => '模擬レッスン1', 'span' => '9/14–9/20',
                'play' => '50〜60分', 'split' => '25分＋5分休＋25分',
                'tone' => '音の立ち上がりと終わり。弓を置いてから鳴らす',
                'read' => 'クリスマス曲集から易しい未知8小節を初見',
                'book' => '先生に見せる2曲を固定：カノン＋篠崎短曲',
                'test' => '2曲を同じ日に録る。各曲の修正点を1つに絞る',
                'gate' => '問題・原因仮説・質問を各1つ書ける',
                'scale' => 'G/D/A 日替わり', 'bpm' => 69, 'canon' => '通し＋録画',
                'cue' => '縮こまらない。堂々と弾く',
            ],
            9 => [
                'phase' => '修正', 'theme' => '弱点の最終修正', 'span' => '9/21–9/27',
                'play' => '50〜60分', 'split' => '25分＋5分休＋25分',
                'tone' => '緊張時も肩・親指・顎をゆるめる。腹式呼吸を練習へ組み込む',
                'read' => 'ページ／段の切替を先読み。開始前に終点を確認',
                'book' => '模擬レッスン2：音階→短い初見→2曲→質問',
                'test' => '録画1テイク。失敗しても続け、回復する能力を評価',
                'gate' => '模擬レッスン2を完了。新しい課題を増やさない',
                'scale' => '見せる1〜2調', 'bpm' => 69, 'canon' => '本番形',
                'cue' => '「無になろう」と思わない。目の前の1小節を頑張る',
            ],
            10 => [
                'phase' => '仕上げ', 'theme' => 'テーパー', 'span' => '9/28–10/1',
                'play' => '35〜45分', 'split' => '短く高品質',
                'tone' => '疲労を抜く。新技術・新曲・速度更新なし',
                'read' => '譜面の書込みを整理。1拍先だけ確認',
                'book' => '音階1〜2調、カノン、篠崎短曲。最終録画と質問3つ',
                'test' => '初回レッスンセット完成。予約確認／連絡',
                'gate' => 'レッスン用の音を残す。10/1は試験ではなく再出発',
                'scale' => '最も安定する調', 'bpm' => 66, 'canon' => '仕上げ',
                'cue' => '縮こまらない。堂々と弾く',
            ],
        ];
    }

    /**
     * @return array<int, ProgramWeek> week_number => week
     */
    private function createPhasesAndWeeks(ProgramVersion $version): array
    {
        $phaseDefs = [
            ['再接触', PhaseIntent::Base, [1, 2], '楽器と身体の再会 → 音の均一。痛み・しびれが出ないことが唯一の通過条件。'],
            ['技術構築', PhaseIntent::Intensify, [3, 4, 5, 6], '左手 → 移弦 → 接続 → フレーズ。時間・テンポ・難易度は同じ週に同時に上げない。'],
            ['再現性・模擬', PhaseIntent::Test, [7, 8, 9], '3回中2回の再現 → 模擬レッスン1 → 弱点の最終修正。新しい課題を増やさない。'],
            ['仕上げ', PhaseIntent::Taper, [10], '疲労を抜く。新技術・新曲・速度更新なし。10/1は試験ではなく再出発。'],
        ];

        $table = $this->weeklyTable();
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
                $week = $table[$weekNumber];

                $weeks[$weekNumber] = $version->weeks()->create([
                    'program_phase_id' => $phase->id,
                    'week_number' => $weekNumber,
                    'starts_on' => $version->starts_on->copy()->addWeeks($weekNumber - 1),
                    'intent' => sprintf('%s｜%s（実奏%s・%s）', $week['phase'], $week['theme'], $week['play'], $week['split']),
                ]);
            }
        }

        return $weeks;
    }

    /**
     * @param  array<string, RoutineItem>  $items
     */
    private function createDays(ProgramVersion $version, array $items): void
    {
        // ---- DAY A 月: 音・基礎＋カノン ----
        $day = $this->createDay($version, 'VIOLIN-A', '音・基礎＋カノン', 1, DayPriorityTier::NeverCut, 45, 60, 1,
            purpose: '週で最も丁寧な音を作る日。時間があるからこそ、速度ではなく音の質へ使う。',
            gate: '音階1調＋カノン2〜4小節で『昨日より良い1音』が言える');
        $this->addStep($day, '身体＋調弦', ProgramStepKind::Preparation, 1, $items['身体＋調弦'], 5,
            '呼吸、肩甲帯、前腕、指。楽器なしで準備してから構える', RequiredLevel::Required, ['cue']);
        $this->addStep($day, '開放弦', ProgramStepKind::Technique, 2, $items['開放弦'], 7,
            '全弓4拍×各弦4往復。弓を置く→まっすぐ→一定の響き→静かに終える', RequiredLevel::Required, ['tone']);
        $this->addStep($day, '音階', ProgramStepKind::Technique, 3, $items['音階（小野アンナ）'], 12,
            '小野アンナ1調。音程か弓のどちらか一方だけを目的にする', RequiredLevel::Required, ['scale']);
        $this->addStep($day, '譜読み', ProgramStepKind::Reading, 4, $items['譜読み'], 5,
            '音名20問または未知8小節。目は1拍先、拍は止めない', RequiredLevel::Recommended, ['read']);
        $this->addStep($day, 'カノン', ProgramStepKind::Repertoire, 5, $items['カノン'], 18,
            '通しより問題区間。4音セル→2〜4小節→前後接続', RequiredLevel::Required, ['canon']);
        $this->addStep($day, '通し／録音', ProgramStepKind::Review, 6, $items['通し／録音'], 5,
            'ミスしても続ける。止まらないことが目的', RequiredLevel::Recommended);
        $this->addStep($day, '記録', ProgramStepKind::Review, 7, $items['練習記録'], 2,
            'よかった1音／直した1点／次回の1点／痛み0〜10', RequiredLevel::Required);

        // ---- DAY B 火: ミニ技術（胸トレ後） ----
        $day = $this->createDay($version, 'VIOLIN-B', 'ミニ技術', 2, DayPriorityTier::CutOk, 30, 30, 2,
            purpose: '胸トレ後。右肩の疲労を増やさない。強い発音を避け、局所修正に絞る。',
            gate: '問題箇所を1つ特定し、原因と次の方法を試した');
        $this->addStep($day, '身体', ProgramStepKind::Preparation, 1, $items['身体（呼吸・肩甲帯・前腕）'], 3,
            '首・肩・肘・手首を小さく動かす。痛みチェック', RequiredLevel::Required, ['cue']);
        $this->addStep($day, '開放弦', ProgramStepKind::Technique, 2, $items['開放弦'], 4,
            '4拍の全弓。今日の1音を『きれいに始め、保ち、終える』', RequiredLevel::Required, ['tone']);
        $this->addStep($day, '音階', ProgramStepKind::Technique, 3, $items['音階（小野アンナ）'], 8,
            '1調、遅いテンポ。音程だけを目的にする', RequiredLevel::Required, ['scale']);
        $this->addStep($day, '4音セル', ProgramStepKind::Technique, 4, $items['4音セル'], 10,
            '難所を4音へ切る。①リズムのみ開放弦 ②左手無音 ③合わせる ④前後1音ずつ足す', RequiredLevel::Required);
        $this->addStep($day, '記録', ProgramStepKind::Review, 5, $items['練習記録'], 2,
            '問題→原因→次の方法を1行で', RequiredLevel::Required);
        $this->addStep($day, '調整', ProgramStepKind::Repertoire, 6, $items['既習曲（週替わり復元）'], 3,
            '時間が余れば既習曲。疲労なら終了してよい', RequiredLevel::Skippable);

        // ---- DAY C 水: 譜読み＋篠崎 ----
        $day = $this->createDay($version, 'VIOLIN-C', '譜読み＋篠崎', 3, DayPriorityTier::Keep, 45, 60, 3,
            purpose: '新しい譜面の日。止まらない初見と、止まって直す練習を混ぜない。',
            gate: '未知8小節を止まらず1回読み、2回目で1箇所直した');
        $this->addStep($day, '音名', ProgramStepKind::Reading, 1, $items['音名フラッシュ'], 3,
            'ト音記号の20音を声に出す。18/20を3秒以内（W6以降は2秒）', RequiredLevel::Required, ['cue']);
        $this->addStep($day, 'リズム', ProgramStepKind::Reading, 2, $items['リズム（手拍子・開放弦）'], 3,
            '音程を捨て、手拍子／開放弦で拍を保つ。♩=50〜60', RequiredLevel::Recommended);
        $this->addStep($day, '先読み', ProgramStepKind::Reading, 3, $items['先読み（30秒スキャン→初見）'], 4,
            '30秒スキャン→目印を1拍先→初見1回→2回目だけ修正', RequiredLevel::Required, ['read']);
        $this->addStep($day, '篠崎', ProgramStepKind::Repertoire, 4, $items['篠崎（短曲）'], 20,
            '短曲。初見→修正→仕上げの全工程を回す', RequiredLevel::Required, ['book']);
        $this->addStep($day, '音階', ProgramStepKind::Technique, 5, $items['音階（小野アンナ）'], 10,
            '当該週の調。開放弦共鳴またはドローンで確認', RequiredLevel::Required, ['scale']);
        $this->addStep($day, '既習曲', ProgramStepKind::Repertoire, 6, $items['既習曲（週替わり復元）'], 10,
            '週替わりの復元曲。愛のあいさつ／セナのピアノ', RequiredLevel::Skippable);
        $this->addStep($day, '記録', ProgramStepKind::Review, 7, $items['練習記録'], 3,
            '停止回数と次の課題', RequiredLevel::Required);

        // ---- DAY B2 木: ミニ修正（背中トレ後） ----
        $day = $this->createDay($version, 'VIOLIN-B2', 'ミニ修正', 4, DayPriorityTier::CutOk, 30, 30, 4,
            purpose: '背中トレ後。握力疲労が強い日は10分版へ落としてよい。',
            gate: '月・水のエラーを1つ潰し、通しへ戻した');
        $this->addStep($day, '開放弦', ProgramStepKind::Technique, 1, $items['開放弦'], 4,
            '弓の直進と均一な音。返しの瞬間を速めない', RequiredLevel::Required, ['cue', 'tone']);
        $this->addStep($day, '音階', ProgramStepKind::Technique, 2, $items['音階（小野アンナ）'], 7,
            '1調。移弦の前に肘を先へ', RequiredLevel::Required, ['scale']);
        $this->addStep($day, 'エラー1箇所', ProgramStepKind::Technique, 3, $items['エラー1箇所'], 12,
            '月・水で見つけた問題を1つだけ。原因を1つに絞る', RequiredLevel::Required);
        $this->addStep($day, '止まらない1回', ProgramStepKind::Repertoire, 4, $items['止まらない1回'], 5,
            '修正した箇所を前後へ接続して通す', RequiredLevel::Required);
        $this->addStep($day, '記録', ProgramStepKind::Review, 5, $items['練習記録'], 2,
            '次回の開始地点を残す', RequiredLevel::Required);

        // ---- DAY D 金: 音楽性＋録音 ----
        $day = $this->createDay($version, 'VIOLIN-D', '音楽性＋録音', 5, DayPriorityTier::Keep, 40, 45, 5,
            purpose: '週の成果を外から聴く日。撮り直しは最大2回。正解が出るまで撮らない。',
            gate: '90秒録音＋5項目の自己評価＋修正点1つ');
        $this->addStep($day, '身体', ProgramStepKind::Preparation, 1, $items['身体（呼吸・肩甲帯・前腕）'], 4,
            '呼吸を長く。腹式呼吸をここで練習しておく', RequiredLevel::Required, ['cue']);
        $this->addStep($day, '開放弦', ProgramStepKind::Technique, 2, $items['開放弦'], 5,
            '弓速／接点／圧力のうち1変数だけ', RequiredLevel::Required, ['tone']);
        $this->addStep($day, '音階', ProgramStepKind::Technique, 3, $items['音階（小野アンナ）'], 8,
            '長い音→2音スラー。弓の配分を意識', RequiredLevel::Required, ['scale']);
        $this->addStep($day, '主曲', ProgramStepKind::Repertoire, 4, $items['カノン'], 15,
            'カノン。フレーズ終点まで逆算して弓を配る', RequiredLevel::Required, ['canon']);
        $this->addStep($day, '補助曲', ProgramStepKind::Repertoire, 5, $items['篠崎（短曲）'], 8,
            '短いフレーズ単位で', RequiredLevel::Recommended, ['book']);
        $this->addStep($day, '録音＋自己評価', ProgramStepKind::Review, 6, $items['録音＋自己評価'], 5,
            '90秒。身体／拍／音／音程／表現の順に見る', RequiredLevel::Required);

        // ---- DAY E 土: 観察・非実奏（投球日） ----
        $day = $this->createDay($version, 'VIOLIN-E', '観察・非実奏', 6, DayPriorityTier::CutOk, 10, 20, 6,
            purpose: '投球日。肩・肘・前腕を守る。基本は見る・聴く・歌う。',
            gate: '動画観察＋譜面スキャン＋歌う。次に試す1点を1つ決めた',
            isOptional: true);
        $this->addStep($day, '音だけ', ProgramStepKind::Reading, 1, $items['動画観察（音だけ）'], 5,
            '画面を見ず30〜60秒。立ち上がり、方向、息、到着点を言葉にする', RequiredLevel::Required, ['cue']);
        $this->addStep($day, '動きだけ', ProgramStepKind::Reading, 2, $items['動画観察（動きだけ）'], 5,
            '無音で同じ箇所。弓の場所・量、身体の先行、余計な力の少なさ', RequiredLevel::Recommended);
        $this->addStep($day, '音＋動き', ProgramStepKind::Reading, 3, $items['動画観察（音＋動き）'], 5,
            '合わせて見る。今週試す1点だけを決める', RequiredLevel::Required);
        $this->addStep($day, '任意：軽奏', ProgramStepKind::Technique, 4, $items['軽奏（無症状時のみ）'], 5,
            '肩・肘・前腕が完全に楽な時のみ。開放弦＋既習曲', RequiredLevel::Skippable,
            abortCondition: '投球後に肩・肘・前腕が張るなら実奏なし。譜面・聴音だけ（V6）');

        // ---- DAY F 日: 週次テスト＋好きな曲 ----
        $day = $this->createDay($version, 'VIOLIN-F', '週次テスト＋好きな曲', 7, DayPriorityTier::NeverCut, 45, 60, 7,
            purpose: '翌週の課題を1つ決める日。数字より『何を直せたか』を見る。',
            gate: '音・音階・初見・2曲＋次週の課題1つ');
        $this->addStep($day, '身体', ProgramStepKind::Preparation, 1, $items['身体（呼吸・肩甲帯・前腕）'], 5,
            '疲労が高ければ脚トレより先に', RequiredLevel::Required, ['cue']);
        $this->addStep($day, '音階テスト', ProgramStepKind::Review, 2, $items['音階テスト'], 10,
            '3回連続で止まらず弾けたか。調と開始bpmを記録', RequiredLevel::Required, ['scale', 'test']);
        $this->addStep($day, '初見テスト', ProgramStepKind::Review, 3, $items['初見テスト'], 8,
            '未知8小節。停止回数を数える', RequiredLevel::Required, ['read']);
        $this->addStep($day, '主曲', ProgramStepKind::Repertoire, 4, $items['カノン'], 15,
            'カノン。止まった場所／直った場所を記録', RequiredLevel::Required, ['canon']);
        $this->addStep($day, '補助曲', ProgramStepKind::Repertoire, 5, $items['篠崎（短曲）'], 10,
            '到達区間を記録', RequiredLevel::Recommended, ['book']);
        $this->addStep($day, '好きな曲', ProgramStepKind::Repertoire, 6, $items['好きな曲'], 5,
            '楽しむための時間。基礎だけで愛情を枯らさない', RequiredLevel::Recommended);
        $this->addStep($day, '翌週計画', ProgramStepKind::Review, 7, $items['翌週計画（Green／Yellow／Red）'], 5,
            'Green／Yellow／Red 判定と、来週の1点', RequiredLevel::Required, ['gate']);

        // ---- DAY X 任意: 10分フォールバック ----
        $day = $this->createDay($version, 'VIOLIN-X', '10分フォールバック', null, DayPriorityTier::CutOk, 10, 10, 8,
            purpose: '予定を実行できない日の短縮モード。失敗ではなく、同じ日の別の形。',
            gate: '1音＋1調＋1フレーズ＋1行。これも『接触日』として数える',
            isOptional: true,
            note: '疲労・残業・睡眠不足の日に、その日のDAYと差し替えて使う（V7）。'
                .'疲労は自動判定できないため自動生成では選ばれない（assignment_mode=fallback）。何度でも使える。');
        $this->addStep($day, '身体＋調弦', ProgramStepKind::Preparation, 1, $items['身体＋調弦'], 2,
            '痛みチェック。Redなら弾かない', RequiredLevel::Required, ['cue']);
        $this->addStep($day, '開放弦', ProgramStepKind::Technique, 2, $items['開放弦'], 2,
            '良い音を1つ作る', RequiredLevel::Required);
        $this->addStep($day, '音階', ProgramStepKind::Technique, 3, $items['音階（小野アンナ）'], 2,
            '1調、ゆっくり', RequiredLevel::Required, ['scale']);
        $this->addStep($day, '曲', ProgramStepKind::Repertoire, 4, $items['カノン'], 2,
            'カノン等を1フレーズ', RequiredLevel::Required, ['canon']);
        $this->addStep($day, '譜面を見て歌う', ProgramStepKind::Reading, 5, $items['譜面を見て歌う'], 1,
            '弾かずに読む', RequiredLevel::Recommended);
        $this->addStep($day, '記録', ProgramStepKind::Review, 6, $items['練習記録'], 1,
            '明日の開始地点', RequiredLevel::Required);
    }

    private function createDay(
        ProgramVersion $version,
        string $code,
        string $name,
        ?int $weekday,
        DayPriorityTier $tier,
        int $minMinutes,
        int $maxMinutes,
        int $sortOrder,
        string $purpose = '',
        string $gate = '',
        bool $isOptional = false,
        ?string $note = null,
    ): ProgramDayTemplate {
        return $version->dayTemplates()->create([
            'code' => $code,
            'name' => $name,
            'priority_tier' => $tier,
            'assignment_mode' => $weekday === null ? DayAssignmentMode::Fallback : DayAssignmentMode::WeekdayFixed,
            'fixed_weekday' => $weekday,
            'estimated_minutes_min' => $minMinutes,
            'estimated_minutes_max' => $maxMinutes,
            'is_optional' => $isOptional,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'note' => trim(sprintf("%s\n通過条件: %s\n%s", $purpose, $gate, $note ?? '')),
        ]);
    }

    /**
     * 1 STEP = 1 種目（音楽は「開放弦を7分」のように種目と時間が1対1で対応する）。
     *
     * @param  list<string>  $tags  週別処方を載せるタグ
     */
    private function addStep(
        ProgramDayTemplate $day,
        string $name,
        ProgramStepKind $kind,
        int $sortOrder,
        RoutineItem $item,
        int $minutes,
        string $cues,
        RequiredLevel $requiredLevel,
        array $tags = [],
        ?string $abortCondition = null,
    ): ProgramDayStep {
        $step = $day->steps()->create([
            'name' => $name,
            'step_kind' => $kind,
            'sort_order' => $sortOrder,
            'required_level' => $requiredLevel,
            'estimated_minutes' => $minutes,
            'abort_condition' => $abortCondition,
        ]);

        $stepItem = $step->items()->create([
            'routine_item_id' => $item->id,
            'sort_order' => 1,
            'amount_value' => $minutes,
            'amount_unit' => '分',
            'load_unit' => in_array('scale', $tags, true) ? 'bpm' : null,
            'cues' => $cues,
            'required_level' => $requiredLevel,
            'progression_mode' => $tags === [] ? ProgressionMode::Fixed : ProgressionMode::Weekly,
            'abort_condition' => $abortCondition,
        ]);

        foreach ($tags as $tag) {
            $this->tagged[$tag][] = $stepItem;
        }

        return $step;
    }

    /**
     * タグ付きステップ種目へ、10週分の週別処方を作る。
     *
     * 同じ種目に複数タグが付く場合は1件へ合成する（週×種目は一意制約）。
     *
     * @param  array<int, ProgramWeek>  $weeks
     */
    private function createWeekPrescriptions(array $weeks): void
    {
        $table = $this->weeklyTable();

        /** @var array<string, array{label: string, field: string}> $tagDefs */
        $tagDefs = [
            'cue' => ['label' => '今週のCUE', 'field' => 'cue'],
            'tone' => ['label' => '音・身体', 'field' => 'tone'],
            'scale' => ['label' => '音階', 'field' => 'scale'],
            'read' => ['label' => '譜読み', 'field' => 'read'],
            'book' => ['label' => '教本・曲', 'field' => 'book'],
            'canon' => ['label' => 'カノン', 'field' => 'canon'],
            'test' => ['label' => '週末テスト', 'field' => 'test'],
            'gate' => ['label' => '進級条件', 'field' => 'gate'],
        ];

        foreach ($weeks as $weekNumber => $week) {
            $plan = $table[$weekNumber];

            /** @var array<string, array{labels: list<string>, notes: list<string>, item: ProgramStepItem, bpm: int|null, isTest: bool}> $rows */
            $rows = [];

            foreach ($tagDefs as $tag => $definition) {
                foreach ($this->tagged[$tag] ?? [] as $stepItem) {
                    $rows[$stepItem->id] ??= [
                        'item' => $stepItem,
                        'labels' => [],
                        'notes' => [],
                        'bpm' => null,
                        'isTest' => false,
                    ];

                    $rows[$stepItem->id]['labels'][] = $definition['label'];
                    $rows[$stepItem->id]['notes'][] = $plan[$definition['field']];

                    if ($tag === 'scale') {
                        $rows[$stepItem->id]['bpm'] = $plan['bpm'];
                    }

                    if ($tag === 'test') {
                        $rows[$stepItem->id]['isTest'] = true;
                    }
                }
            }

            foreach ($rows as $row) {
                // タグが1つなら intent=ラベル / note=内容 に分ける（プラン生成時に
                // 「ラベル：内容」へ合成される）。複数タグをそのまま連結すると
                // ラベルと内容が1対1で対応しなくなるため、処方の中でラベルを付けて
                // 1項目ずつに分けておく。
                $isSingle = count($row['labels']) === 1;

                $week->itemPrescriptions()->create([
                    'program_step_item_id' => $row['item']->id,
                    'fixed_load' => $row['bpm'],
                    'is_test' => $row['isTest'],
                    'intent' => $isSingle ? $row['labels'][0] : null,
                    'note' => $isSingle
                        ? $row['notes'][0]
                        : implode(' / ', array_map(
                            static fn (string $label, string $text): string => $label.'：'.$text,
                            $row['labels'],
                            $row['notes'],
                        )),
                ]);
            }
        }
    }

    private function createConstraints(ProgramVersion $version): void
    {
        $constraints = [
            ['contact_priority', '毎日接触・実奏は週6日。実行できない日は10分フォールバック（VIOLIN-X）へ落とし、休んだ日ではなく『接触日』として数える。', ['contact_days_per_week' => 7, 'playing_days_per_week' => 6]],
            ['single_axis_progression', '進級禁止（1軸ルール）: 同じ週に「時間＋テンポ＋難しい曲」を同時に上げない。50分へ延ばす週はテンポ据え置き。新曲を始める週は総時間据え置き。上げるのは原則1軸。', null],
            ['tempo_progression', 'テンポ進行ルール: 正しいテンポ＝3回連続で今日の目的（音程・リズム・脱力のいずれか）が達成できる速さ。3回連続成功で+4bpm、2回続けて崩れたら-6bpmまたは区間を半分にする。通し弾きと修正練習のテンポは分ける。遅い練習でも音楽的な方向と弓の配分は残す。', ['increase_bpm' => 4, 'decrease_bpm' => 6, 'success_streak' => 3, 'failure_streak' => 2]],
            ['no_proof_design', '「証明しない設計」: 動画を毎週必須にしない／点数競争にしない／欠席週を赤く表示しない／連続日数を最重要指標にしない／難曲の期限を急かさない。週次比較は上達の証明ではなく「次に何を大切にするか決める時間」。残すのは成果ではなく『次にやる1点』。', null],
            ['practice_cue_limit', 'Practice Cue は1日1つ（多くても2つ）だけ選ぶ。教えを全部チェックリストにすると「思いすぎて弾けない」状態になる。保存する層と今日使う層を分ける。', ['max_cues_per_day' => 2]],
            ['violin_principles', 'ヴァイオリンの7原則。', ['principles' => [
                'ちゃんと音を出す。やりすぎくらい発音する',
                '出した音の先を描く。発音後の響きまでイメージする',
                '次の動作を先に準備する。移弦は準備してから発車する',
                '遅くなっても止まらず、丁寧に進む',
                '上手くなった証明をしようとしない。細く長く、勝手に上手くなる',
                '縮こまらず堂々と弾く。ミスを恐れて小さくならない',
                '一度に全部直さない。今日の1点だけを頑張る',
            ]]],
            ['bow_technique', '弓の技術（弓速一定／返し／配分／移弦の準備）。カノンがこの4つを練習する主教材。', ['topics' => [
                ['name' => '弓速を一定にする', 'why' => '発音した瞬間の弓速が変わると、音が途切れて聞こえる', 'check' => '目を閉じて4往復。音量の段差を感じたら速度が乱れている'],
                ['name' => '弓の返し', 'why' => '返しの直前に弓速が上がると、そこで音が割れる／切れる', 'check' => '返しの前後で音量が同じか。「ガリッ」「フッ」が鳴らないか'],
                ['name' => '弓の配分', 'why' => '最初の音で弓を使いすぎると、フレーズの終点で音が消える', 'check' => 'フレーズ終点で弓が足りているか'],
                ['name' => '移弦の準備', 'why' => '移弦してから肘を動かすと、弓が暴れて音が濁る', 'check' => '移弦の瞬間に音が飛ばない。2本の弦が同時に鳴らない'],
            ]]],
            ['one_note_loop', '1音ループ: HEAR（鳴る前に聴く）→ PLACE（弓と指を先に準備）→ PLAY（始まり・中身・終わりを一つの音として）→ LISTEN（評価語を1つだけ）→ DECIDE（次の1回で変える変数を1つ選ぶ）。', ['steps' => ['HEAR', 'PLACE', 'PLAY', 'LISTEN', 'DECIDE']]],
            ['score_rubric', '1音の評価5点: 準備／立ち上がり／持続／音程／終わり。各3段階で見る。', ['axes' => ['準備', '立ち上がり', '持続', '音程', '終わり']]],
            ['canon_focus', 'カノン（主曲）: 以前の到達点を新しい基礎で作り直す。43小節目〜は上から降ってくる音型＝讃える賛歌 → 静かに落ち着いて終わる。ナチュラルの箇所で左手の力を抜く。記録は「43小節目を5回」ではなく「賛歌の入りを、弓速一定で5回」と書く。', null],
            ['song_roadmap', '曲ロードマップ（北極星を含む）。期限を急かさない。', ['songs' => [
                ['title' => '海の見える街', 'level' => 'ok'],
                ['title' => 'ひまわり', 'level' => 'ok'],
                ['title' => '彼こそが海賊', 'level' => 'mid'],
                ['title' => '情熱大陸', 'level' => 'mid'],
                ['title' => 'Bach BWV1060', 'level' => 'mid'],
                ['title' => 'Brahms Sonata 3 - II', 'level' => 'hard'],
                ['title' => 'Tchaikovsky Concerto 冒頭', 'level' => 'hard'],
            ]]],
            ['pre_performance_ritual', '本番前ルーティン: 腹式呼吸（4秒吸う→7秒止める→8秒吐く を2〜3回）／軽いストレッチ／「無になろう」「いつも通り」と考えない／最初の音の発音だけ準備する／目の前の1小節を頑張る／ミスしても後ろを振り返らない／遅くても丁寧に、止まらず進む／堂々と弾く。', null],
            ['exit_criteria', '10/1の合格ライン。合格は完璧な演奏ではなく、先生が次を設計できる状態。', ['criteria' => [
                ['name' => '継続', 'line' => '10週中8週以上で週5接触、うち週4実奏'],
                ['name' => '演奏耐性', 'line' => '45〜60分を休憩込みで行い、演奏中・終了後・翌朝に症状なし'],
                ['name' => '音', 'line' => '開放弦4拍×4回のうち3回、狙った立ち上がりと持続'],
                ['name' => '弓速', 'line' => '1弓の中で速度が一定。返しで音が切れない'],
                ['name' => '移弦', 'line' => '肘を先に準備してから弦を渡れる'],
                ['name' => '音階', 'line' => 'G/D/A長調の1オクターブを少なくとも2調、♩=60以下で3回連続'],
                ['name' => '音名', 'line' => '第1ポジション周辺の20音中18音を2秒前後で認識'],
                ['name' => '先読み', 'line' => '易しい未知8小節を1拍先意識で止まらず演奏'],
                ['name' => '主曲', 'line' => 'カノンを無理のないテンポで最後まで続け、ミスから復帰'],
                ['name' => '補助曲', 'line' => '篠崎2巻の短曲1曲を、譜面を見て通す'],
                ['name' => '自己調整', 'line' => '問題小節・原因・試した方法を言葉で説明できる'],
            ]]],
            ['v1_neural', 'V1 神経症状: しびれ、ピリピリ、感覚低下、握りにくさ → 即中止。続く／再発なら医療相談。', null],
            ['v2_pain', 'V2 痛み: 演奏中2/10超、終了後増加、翌朝残る → 前段階へ戻す。48時間で引かないなら相談。', ['pain_threshold' => 2]],
            ['v3_posture', 'V3 姿勢: 肩が上がる、顎で挟む、右手親指が固まる → 楽器を置き、呼吸。再開して同じなら終了。', null],
            ['v4_time', 'V4 時間: 予定を超えて『もう少し』を続ける → タイマーで終了。翌日へ繰越さない。', null],
            ['v5_triple_axis', 'V5 三軸同時増: 時間・テンポ・難易度を同じ週に上げる → 1軸だけ上げ、他は据え置き。', null],
            ['v6_throwing_day', 'V6 投球日: 土曜の投球後に肩・肘・前腕が張る → 実奏なし。譜面・聴音だけ。', ['day_code' => 'VIOLIN-E']],
            ['v7_fatigue', 'V7 疲労: 睡眠不足、高疲労、集中が切れ音を雑に通過 → 10分フォールバック（VIOLIN-X）または完全休養。', ['day_code' => 'VIOLIN-X']],
            ['v8_untaught', 'V8 未習技術: ビブラート、ポジション移動、重音等を自己流で増やす → 保留し、動画と質問を先生へ。', null],
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
