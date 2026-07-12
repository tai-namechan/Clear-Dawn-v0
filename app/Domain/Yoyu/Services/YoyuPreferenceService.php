<?php

namespace App\Domain\Yoyu\Services;

use App\Domain\Yoyu\Models\YoyuPreference;
use App\Domain\Yoyu\Support\YoyuTravelConstants;
use App\Models\User;

/**
 * Load (or default) per-user prep/buffer minutes.
 *
 * @phpstan-type TravelLead array{prep_minutes: int, buffer_minutes: int}
 */
final class YoyuPreferenceService
{
    /**
     * @return TravelLead
     */
    public function travelLeadFor(User $user): array
    {
        $pref = YoyuPreference::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->first();

        if ($pref === null) {
            return [
                'prep_minutes' => YoyuTravelConstants::PREP_MINUTES,
                'buffer_minutes' => YoyuTravelConstants::BUFFER_MINUTES,
            ];
        }

        return [
            'prep_minutes' => $pref->prep_minutes,
            'buffer_minutes' => $pref->buffer_minutes,
        ];
    }

    /**
     * @return TravelLead
     */
    public function upsertTravelLead(User $user, int $prepMinutes, int $bufferMinutes): array
    {
        /** @var YoyuPreference $pref */
        $pref = YoyuPreference::query()
            ->withoutUserScope()
            ->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'prep_minutes' => $prepMinutes,
                    'buffer_minutes' => $bufferMinutes,
                ],
            );

        return [
            'prep_minutes' => (int) $pref->prep_minutes,
            'buffer_minutes' => (int) $pref->buffer_minutes,
        ];
    }
}
