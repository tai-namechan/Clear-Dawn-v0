<?php

namespace App\Domain\Connectors\Calendar;

use App\Domain\Connectors\Jobs\SyncGoogleCalendarJob;
use App\Domain\Kioku\Models\Connector;
use App\Models\User;

/**
 * Web-side staleness check: only reads the DB and dispatches; never talks
 * to Google synchronously. Duplicate dispatches collapse via ShouldBeUnique.
 */
final class CalendarSyncCoordinator
{
    public function syncIfStale(User $user): void
    {
        $connector = Connector::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('source_type', Connector::SOURCE_GOOGLE_CALENDAR)
            ->first();

        if ($connector === null || $connector->status === 'revoking') {
            return;
        }

        // Reauthorization errors need the user, not another sync attempt.
        if ($connector->last_error_code === 'reauthorization_required') {
            return;
        }

        $ttlMinutes = (int) config('calendar.sync_ttl_minutes', 15);
        $isFresh = $connector->last_synced_at !== null
            && $connector->last_synced_at->isAfter(now()->subMinutes($ttlMinutes));

        if ($isFresh) {
            return;
        }

        // Debounce repeated page views while a sync attempt is underway.
        $recentlyAttempted = $connector->last_sync_attempt_at !== null
            && $connector->last_sync_attempt_at->isAfter(now()->subMinutes(2));

        if ($recentlyAttempted) {
            return;
        }

        SyncGoogleCalendarJob::dispatch($connector->id);
    }

    public function forceSync(User $user): bool
    {
        $connector = Connector::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('source_type', Connector::SOURCE_GOOGLE_CALENDAR)
            ->first();

        if ($connector === null || $connector->status === 'revoking') {
            return false;
        }

        SyncGoogleCalendarJob::dispatch($connector->id);

        return true;
    }
}
