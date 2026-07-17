<?php

namespace App\Console\Commands;

use App\Domain\Yoyu\Money\Services\MoneyCsvImportService;
use Illuminate\Console\Command;

class PurgeYoyuMoneyImportsCommand extends Command
{
    protected $signature = 'yoyu-money:purge-imports';

    protected $description = 'Delete completed/rolled-back Yoyu Money CSV import files older than 7 days';

    public function handle(MoneyCsvImportService $importService): int
    {
        $purged = $importService->purgeExpired();
        $this->info("Purged {$purged} expired import file(s).");

        return self::SUCCESS;
    }
}
