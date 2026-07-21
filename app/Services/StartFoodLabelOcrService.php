<?php

namespace App\Services;

use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\QuotaExceededException;
use App\Enums\FoodLookupStatus;
use App\Jobs\LookupFoodLabelOcrJob;
use App\Models\FoodLookupRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * 成分表OCRの起点（設計 §13.4 手順1〜4）。
 * 入口1: F1 miss（not_found / failed）の lookup へ画像を添付して再解析。
 * 入口2: バーコードなしで画像から新規 lookup を作成。
 * どちらも quota 事前チェック → private storage 保存 → ocr_pending 遷移 →
 * afterCommit で OCR Job dispatch。画像は終端状態（found/failed）で Job が破棄する。
 */
class StartFoodLabelOcrService
{
    public function __construct(
        private readonly AiGateway $ai,
    ) {}

    /**
     * @throws QuotaExceededException
     * @throws ModelNotFoundException
     */
    public function attachToLookup(User $user, string $lookupId, UploadedFile $image): FoodLookupRequest
    {
        $lookup = FoodLookupRequest::query()
            ->where('user_id', $user->id)
            ->whereKey($lookupId)
            ->whereIn('status', [FoodLookupStatus::NotFound, FoodLookupStatus::Failed])
            ->firstOrFail();

        $this->ai->assertWithinQuota($user->id);

        $previousImagePath = $lookup->temp_image_path;
        $path = $this->storeImage($user, $image);

        $claimed = $this->runDeletingOnFailure($path, function () use ($lookup, $user, $path): bool {
            return DB::transaction(function () use ($lookup, $user, $path): bool {
                // 条件付きUPDATEで並行アップロード競合を排除（F1/Kioku と同じ claim パターン）
                $claimed = FoodLookupRequest::query()
                    ->whereKey($lookup->id)
                    ->where('user_id', $user->id)
                    ->whereIn('status', [FoodLookupStatus::NotFound->value, FoodLookupStatus::Failed->value])
                    ->update([
                        'status' => FoodLookupStatus::OcrPending->value,
                        'temp_image_path' => $path,
                        'source' => null,
                        'result' => null,
                        'error_code' => null,
                        'expires_at' => now()->addDay(),
                    ]);

                if ($claimed !== 1) {
                    return false;
                }

                DB::afterCommit(fn () => LookupFoodLabelOcrJob::dispatch($lookup->id));

                return true;
            });
        });

        if (! $claimed) {
            Storage::disk($this->disk())->delete($path);

            throw new ConflictHttpException('すでに解析中です。');
        }

        // 再撮影（failed → 再upload）で残っていた前回の画像を破棄
        if ($previousImagePath !== null && $previousImagePath !== $path) {
            Storage::disk($this->disk())->delete($previousImagePath);
        }

        return $lookup->refresh();
    }

    /**
     * @throws QuotaExceededException
     */
    public function startWithoutBarcode(User $user, UploadedFile $image): FoodLookupRequest
    {
        $this->ai->assertWithinQuota($user->id);

        $path = $this->storeImage($user, $image);

        return $this->runDeletingOnFailure($path, function () use ($user, $path): FoodLookupRequest {
            return DB::transaction(function () use ($user, $path): FoodLookupRequest {
                $lookup = FoodLookupRequest::query()->create([
                    'user_id' => $user->id,
                    'barcode' => null,
                    'barcode_type' => null,
                    'status' => FoodLookupStatus::OcrPending,
                    'temp_image_path' => $path,
                    'expires_at' => now()->addDay(),
                ]);

                DB::afterCommit(fn () => LookupFoodLabelOcrJob::dispatch($lookup->id));

                return $lookup;
            });
        });
    }

    private function storeImage(User $user, UploadedFile $image): string
    {
        $path = $image->store('food-label-ocr/'.$user->id, ['disk' => $this->disk()]);

        if ($path === false) {
            throw new \RuntimeException('Failed to store food label image.');
        }

        return $path;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function runDeletingOnFailure(string $path, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            Storage::disk($this->disk())->delete($path);

            throw $e;
        }
    }

    private function disk(): string
    {
        return (string) config('meals.label_ocr.disk', 'local');
    }
}
