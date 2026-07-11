<?php

namespace App\Domain\Yoyu\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cached external calendar event. Timed events use starts_at/ends_at (UTC);
 * all-day events use starts_on/ends_on (local dates, end exclusive).
 *
 * @property string $id
 * @property int $user_id
 * @property string $connector_id
 * @property string $calendar_external_id
 * @property string $external_id
 * @property string|null $i_cal_uid
 * @property string $title
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $starts_on
 * @property string|null $ends_on
 * @property string|null $event_timezone
 * @property bool $all_day
 * @property string $transparency
 * @property string $status
 * @property string|null $location
 * @property Carbon $synced_at
 */
#[Fillable([
    'user_id',
    'connector_id',
    'calendar_external_id',
    'external_id',
    'i_cal_uid',
    'title',
    'starts_at',
    'ends_at',
    'starts_on',
    'ends_on',
    'event_timezone',
    'all_day',
    'transparency',
    'status',
    'location',
    'synced_at',
])]
class YoyuCalendarEvent extends Model
{
    use BelongsToUser, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
