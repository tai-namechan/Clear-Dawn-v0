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
 * Best-effort external revoke, then unconditional local cleanup.
 * Idempotent: a vanished connector is a completed disconnect.
 */
class DisconnectGoogleCalendarJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public string $connectorId)
    {
        $this->onQueue('integrations');
    }

    public function handle(): void
    {
        $connector = Connector::query()->withoutUserScope()->find($this->connectorId);
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
            // Best effort only: local cleanup must happen regardless.
        }

        DB::transaction(function () use ($connector): void {
            YoyuCalendarEvent::query()
                ->withoutUserScope()
                ->where('connector_id', $connector->id)
                ->delete();

            $connector->delete();
        });
    }
}
