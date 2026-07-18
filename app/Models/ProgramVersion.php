<?php

namespace App\Models;

use App\Enums\ProgramVersionStatus;
use Database\Factories\ProgramVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * プログラムの版。改訂はコピーオンライトで新版を作る（旧版・実行済み記録は不変）。
 *
 * @property string $id
 * @property string $program_id
 * @property int $version_number
 * @property ProgramVersionStatus $status
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string|null $change_summary
 * @property string|null $change_reason
 * @property Carbon|null $approved_at
 */
#[Fillable([
    'program_id',
    'version_number',
    'status',
    'starts_on',
    'ends_on',
    'change_summary',
    'change_reason',
    'approved_at',
])]
class ProgramVersion extends Model
{
    /** @use HasFactory<ProgramVersionFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProgramVersionStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return HasMany<ProgramPhase, $this>
     */
    public function phases(): HasMany
    {
        return $this->hasMany(ProgramPhase::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProgramWeek, $this>
     */
    public function weeks(): HasMany
    {
        return $this->hasMany(ProgramWeek::class)->orderBy('week_number');
    }

    /**
     * @return HasMany<ProgramDayTemplate, $this>
     */
    public function dayTemplates(): HasMany
    {
        return $this->hasMany(ProgramDayTemplate::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProgramConstraint, $this>
     */
    public function constraints(): HasMany
    {
        return $this->hasMany(ProgramConstraint::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProgramMetricTarget, $this>
     */
    public function metricTargets(): HasMany
    {
        return $this->hasMany(ProgramMetricTarget::class);
    }

    /**
     * @return HasMany<ProgramAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProgramAttachment::class);
    }

    /**
     * 指定日が属する週を返す（範囲外は null）。
     */
    public function weekFor(Carbon $date): ?ProgramWeek
    {
        return $this->weeks->first(
            fn (ProgramWeek $week): bool => $date->betweenIncluded(
                $week->starts_on,
                $week->starts_on->copy()->addDays(6),
            ),
        );
    }
}
