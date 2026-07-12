<?php

namespace App\Domain\Connectors\Jobs;

use App\Domain\Kioku\Models\Connector;
use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Best-effort external revoke, then local cleanup only while the connector
 * remains the disconnect target generation (status=revoking + version match).
 */
class DisconnectGoogleCalendarJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public string $connectorId,
        public int $connectionVersion,
    ) {
        $this->onQueue('integrations');
    }

    public function handle(): void
    {
        $connector = $this->loadRevokingConnector();
        if ($connector === null) {
            return;
        }

        try {
            $token = $connector->refresh_token ?? $connector->access_token;
            if (is_string($token) && $token !== '') {
                Http::asForm()
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->post('https://oauth2.googleapis.com/revoke', ['token' => $token]);
            }
        } catch (Throwable) {
            // Best effort only: local cleanup must happen regardless when still revoking.
        }

        DB::transaction(function (): void {
            $connector = Connector::query()
                ->withoutUserScope()
                ->whereKey($this->connectorId)
                ->where('source_type', Connector::SOURCE_GOOGLE_CALENDAR)
                ->where('connection_version', $this->connectionVersion)
                ->where('status', 'revoking')
                ->lockForUpdate()
                ->first();

            if ($connector === null) {
                // Reconnected (or otherwise left revoking) while revoke HTTP ran.
                return;
            }

            YoyuCalendarEvent::query()
                ->withoutUserScope()
                ->where('connector_id', $connector->id)
                ->delete();

            $connector->delete();
        });
    }

    private function loadRevokingConnector(): ?Connector
    {
        return Connector::query()
            ->withoutUserScope()
            ->whereKey($this->connectorId)
            ->where('source_type', Connector::SOURCE_GOOGLE_CALENDAR)
            ->where('connection_version', $this->connectionVersion)
            ->where('status', 'revoking')
            ->first();
    }
}
