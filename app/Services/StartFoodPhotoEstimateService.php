<?php

namespace App\Services;

use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\QuotaExceededException;
use App\Enums\FoodLookupStatus;
use App\Jobs\EstimateFoodPhotoJob;
use App\Models\FoodLookupRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StartFoodPhotoEstimateService
{
    public function __construct(
        private readonly AiGateway $ai,
    ) {}

    /**
     * @throws QuotaExceededException
     */
    public function start(User $user, UploadedFile $image): FoodLookupRequest
    {
        $this->ai->assertWithinQuota($user->id);

        $path = $this->storeImage($user, $image);

        return $this->runDeletingOnFailure($path, function () use ($user, $path): FoodLookupRequest {
            return DB::transaction(function () use ($user, $path): FoodLookupRequest {
                $lookup = FoodLookupRequest::query()->create([
                    'user_id' => $user->id,
                    'barcode' => null,
                    'barcode_type' => null,
                    'status' => FoodLookupStatus::AiPending,
                    'temp_image_path' => $path,
                    'expires_at' => now()->addDay(),
                ]);

                DB::afterCommit(fn () => EstimateFoodPhotoJob::dispatch($lookup->id));

                return $lookup;
            });
        });
    }

    private function storeImage(User $user, UploadedFile $image): string
    {
        $path = $image->store('food-photo-estimate/'.$user->id, ['disk' => $this->disk()]);

        if ($path === false) {
            throw new \RuntimeException('Failed to store food photo image.');
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
