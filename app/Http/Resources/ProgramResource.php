<?php

namespace App\Http\Resources;

use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * プログラム一覧用サマリ。
 *
 * @mixin Program
 */
class ProgramResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->whenLoaded('activeVersion') ? $this->activeVersion : null;
        $user = $request->user();
        $today = $user instanceof User
            ? app(UserTimezoneResolver::class)->todayDateString($user)
            : app(UserTimezoneResolver::class)->todayDateString(null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'purpose' => $this->purpose,
            'status' => $this->status->value,
            'goal' => $this->whenLoaded('goal', fn () => $this->goal === null ? null : [
                'id' => $this->goal->id,
                'name' => $this->goal->name,
            ]),
            'active_version' => $version === null ? null : [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'starts_on' => $version->starts_on->toDateString(),
                'ends_on' => $version->ends_on->toDateString(),
                'week_count' => $version->weeks->count(),
                'day_count' => $version->dayTemplates->count(),
                'current_week_number' => $version->weekFor(Carbon::parse($today))?->week_number,
            ],
        ];
    }
}
