<?php

namespace App\Models;

use App\Domain\Yoyu\Support\UserTimezoneResolver;
use Database\Factories\PersonalProfileEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 個人プロファイル（有効日つき履歴）。値は import コマンドで投入し、リポジトリに含めない。
 * 過去プログラムの再現性のため、値の更新は「新しい effective_from の行を追加」で行う。
 *
 * @property string $id
 * @property int $user_id
 * @property string $key
 * @property string|null $value_numeric
 * @property string|null $value_text
 * @property array<string, mixed>|null $value_json
 * @property string|null $unit
 * @property Carbon $effective_from
 * @property string|null $source
 * @property string|null $note
 */
#[Fillable([
    'user_id',
    'key',
    'value_numeric',
    'value_text',
    'value_json',
    'unit',
    'effective_from',
    'source',
    'note',
])]
class PersonalProfileEntry extends Model
{
    /** @use HasFactory<PersonalProfileEntryFactory> */
    use HasFactory, HasUlids;

    public const KEY_ONE_RM_BENCH = 'one_rm_bench';

    public const KEY_ONE_RM_SQUAT = 'one_rm_squat';

    public const KEY_ONE_RM_DEADLIFT = 'one_rm_deadlift';

    public const KEY_HEIGHT_CM = 'height_cm';

    public const KEY_INJURY_HISTORY = 'injury_history';

    public const KEY_SAFETY_POLICY = 'safety_policy';

    public const KEY_H7_NEURAL_LOCK = 'h7_neural_symptom_lock';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_numeric' => 'decimal:3',
            'value_json' => 'array',
            'effective_from' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 指定日時点で有効な値（同一 key の最新 effective_from）を返す。
     * $asOf 省略時はユーザー TZ のカレンダー「今日」。
     */
    public static function currentFor(User $user, string $key, ?Carbon $asOf = null): ?self
    {
        $asOfDate = $asOf?->toDateString()
            ?? app(UserTimezoneResolver::class)->todayDateString($user);

        return self::query()
            ->where('user_id', $user->id)
            ->where('key', $key)
            ->where('effective_from', '<=', $asOfDate)
            ->orderByDesc('effective_from')
            ->first();
    }
}
