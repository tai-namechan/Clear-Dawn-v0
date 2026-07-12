<?php

namespace App\Domain\Yoyu\Services;

use App\Domain\Yoyu\Models\YoyuCalendarEvent;

/**
 * Persist app-owned per-event prep/buffer overrides (never touch provider location).
 */
final class YoyuEventTravelLeadService
{
    /**
     * @return array{prep_minutes_override: int|null, buffer_minutes_override: int|null, updated: bool}
     */
    public function upsert(
        int $userId,
        string $externalId,
        ?int $prepMinutes,
        ?int $bufferMinutes,
        bool $clear = false,
    ): array {
        $query = YoyuCalendarEvent::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('external_id', $externalId);

        /** @var YoyuCalendarEvent|null $event */
        $event = $query->first();

        if ($event === null) {
            return [
                'prep_minutes_override' => null,
                'buffer_minutes_override' => null,
                'updated' => false,
            ];
        }

        if ($clear) {
            $event->update([
                'prep_minutes_override' => null,
                'buffer_minutes_override' => null,
            ]);
        } else {
            $event->update([
                'prep_minutes_override' => $prepMinutes,
                'buffer_minutes_override' => $bufferMinutes,
            ]);
        }

        $event->refresh();

        return [
            'prep_minutes_override' => $event->prep_minutes_override,
            'buffer_minutes_override' => $event->buffer_minutes_override,
            'updated' => true,
        ];
    }
}
