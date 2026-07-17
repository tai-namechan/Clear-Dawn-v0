<?php

namespace App\Models;

use Database\Factories\ProgramChoiceGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 選択式メニュー（例: 水曜 = 上半身補助 / ヨガ / ロードワーク / 完全休養）。
 *
 * @property string $id
 * @property string $program_day_template_id
 * @property string $name
 * @property string|null $selection_hint
 */
#[Fillable(['program_day_template_id', 'name', 'selection_hint'])]
class ProgramChoiceGroup extends Model
{
    /** @use HasFactory<ProgramChoiceGroupFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<ProgramDayTemplate, $this>
     */
    public function dayTemplate(): BelongsTo
    {
        return $this->belongsTo(ProgramDayTemplate::class, 'program_day_template_id');
    }

    /**
     * @return HasMany<ProgramChoiceOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProgramChoiceOption::class)->orderBy('sort_order');
    }
}
