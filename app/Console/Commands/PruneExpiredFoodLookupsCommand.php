<?php

namespace App\Console\Commands;

use App\Models\FoodLookupRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExpiredFoodLookupsCommand extends Command
{
    protected $signature = 'meals:prune-expired-lookups';

    protected $description = 'Delete barcode/OCR food lookup requests past their expires_at';

    public function handle(): int
    {
        $disk = (string) config('meals.label_ocr.disk', 'local');
        $deleted = 0;

        // ユーザーがモーダルを閉じて放棄した ocr_pending 等に画像が残るため、
        // 行の削除前に temp 画像を破棄する（PR-F2・完成設計 §3 の安全網）
        FoodLookupRequest::query()
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->chunkById(100, function ($lookups) use ($disk, &$deleted): void {
                foreach ($lookups as $lookup) {
                    if ($lookup->temp_image_path !== null) {
                        Storage::disk($disk)->delete($lookup->temp_image_path);
                    }

                    $lookup->delete();
                    $deleted++;
                }
            });

        $this->info("Pruned {$deleted} expired food lookup request(s).");

        return self::SUCCESS;
    }
}
