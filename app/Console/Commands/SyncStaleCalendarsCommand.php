<?php

namespace App\Console\Commands;

use App\Domain\Connectors\Jobs\SyncGoogleCalendarJob;
use App\Domain\Kioku\Models\Connector;
use Illuminate\Console\Command;

/**
 * Hourly safety net: users who never open Yoyu still get a fresh cache.
 * Duplicate dispatches collapse via the job's ShouldBeUnique lock.
 */
class SyncStaleCalendarsCommand extends Command
{
    protected $signature = 'calendar:sync-stale {--limit=200 : Max connectors per run}';

    protected $description = 'Dispatch sync jobs for Google Calendar connectors with stale caches';

    public function handle(): int
    {
        if (! (bool) config('services.google.calendar_enabled')) {
            $this->info('Google Calendar is disabled; nothing dispatched.');

            return self::SUCCESS;
        }

        $ttlMinutes = (int) config('calendar.sync_ttl_minutes', 15);
        $dispatched = 0;

        Connector::query()
            ->withoutUserScope()
            ->where('source_type', Connector::SOURCE_GOOGLE_CALENDAR)
            ->whereIn('status', ['connected', 'error', 'syncing', 'idle'])
            ->where(fn ($query) => $query
                ->whereNull('last_synced_at')
                ->orWhere('last_synced_at', '<', now()->subMinutes($ttlMinutes)))
            // Reauthorization needs the user; retrying only burns quota.
            ->where(fn ($query) => $query
                ->whereNull('last_error_code')
                ->orWhere('last_error_code', '!=', 'reauthorization_required'))
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get(['id', 'connection_version'])
            ->each(function (Connector $connector) use (&$dispatched): void {
                SyncGoogleCalendarJob::dispatch($connector->id, (int) $connector->connection_version);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} calendar sync job(s).");

        return self::SUCCESS;
    }
}
