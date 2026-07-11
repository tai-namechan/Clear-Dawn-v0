<?php

namespace App\Console\Commands;

use App\Domain\Shared\AI\AiUsageReconciler;
use Illuminate\Console\Command;

class ReconcileAiUsageCommand extends Command
{
    protected $signature = 'ai:usage-reconcile {--period= : YYYY-MM period to reconcile}';

    protected $description = 'Idempotently reconcile ai_usage_monthly spent_usd from logs and expired charges';

    public function handle(AiUsageReconciler $reconciler): int
    {
        $period = $this->option('period');
        if (! is_string($period) || preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            $this->error('Provide --period=YYYY-MM');

            return self::FAILURE;
        }

        $result = $reconciler->reconcilePeriod($period);
        $this->info("Reconciled period {$period}: users={$result['users']} adjusted={$result['adjusted']}");

        return self::SUCCESS;
    }
}
