<?php

namespace App\Console\Commands;

use App\Domain\Shared\AI\AiUsageLedger;
use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReapAiUsageReservationsCommand extends Command
{
    protected $signature = 'ai:usage-reap {--limit=100 : Max rows to process}';

    protected $description = 'Release stale reserved AI usage and expire stale in-flight reservations';

    public function handle(AiUsageLedger $ledger): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $released = 0;
        $expired = 0;

        $reservedCutoff = now()->subMinutes(10);
        $inFlightCutoff = now()->subSeconds((int) config('ai.timeout', 60) + 300);

        AiUsageRequest::query()
            ->withoutUserScope()
            ->where('status', AiUsageRequestStatus::Reserved)
            ->whereNull('provider_started_at')
            ->where('created_at', '<', $reservedCutoff)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (AiUsageRequest $request) use ($ledger, &$released): void {
                try {
                    $ledger->release($request->id, 'stale_reserved');
                    $released++;
                } catch (Throwable $e) {
                    Log::warning('Failed to release stale AI reservation.', [
                        'usage_request_id' => $request->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            });

        $remaining = max(0, $limit - $released);

        if ($remaining > 0) {
            AiUsageRequest::query()
                ->withoutUserScope()
                ->where('status', AiUsageRequestStatus::InFlight)
                ->where('provider_started_at', '<', $inFlightCutoff)
                ->orderBy('id')
                ->limit($remaining)
                ->get()
                ->each(function (AiUsageRequest $request) use ($ledger, &$expired): void {
                    try {
                        $ledger->expire($request->id, 'stale_in_flight');
                        $expired++;
                    } catch (Throwable $e) {
                        Log::warning('Failed to expire stale AI in_flight reservation.', [
                            'usage_request_id' => $request->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                });
        }

        Log::info('AI usage reaper finished.', [
            'released' => $released,
            'expired' => $expired,
        ]);

        $this->info("Reaped AI usage: released={$released} expired={$expired}");

        return self::SUCCESS;
    }
}
