<?php

namespace App\Domain\Yoyu\Money\Jobs;

use App\Domain\Yoyu\Money\Models\MoneyImport;
use App\Domain\Yoyu\Money\Services\MoneyCsvImportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMoneyImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 90];

    public int $uniqueFor = 3600;

    public function __construct(public string $importId) {}

    public function uniqueId(): string
    {
        return $this->importId;
    }

    public function handle(MoneyCsvImportService $importService): void
    {
        $import = MoneyImport::query()->withoutUserScope()->find($this->importId);
        if ($import === null) {
            return;
        }

        $importService->processImport($import);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('ProcessMoneyImportJob failed.', [
            'import_id' => $this->importId,
            'message' => $exception?->getMessage(),
        ]);

        MoneyImport::query()
            ->withoutUserScope()
            ->whereKey($this->importId)
            ->where('status', '!=', 'completed')
            ->update([
                'status' => 'failed',
                'error_message' => $exception?->getMessage(),
                'finished_at' => now(),
            ]);
    }
}
