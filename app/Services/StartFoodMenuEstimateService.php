<?php

namespace App\Services;

use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\QuotaExceededException;
use App\Enums\FoodLookupStatus;
use App\Jobs\EstimateFoodMenuJob;
use App\Models\FoodItem;
use App\Models\FoodLookupRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartFoodMenuEstimateService
{
    public function __construct(
        private readonly AiGateway $ai,
    ) {}

    /**
     * @return array{status: string, food?: FoodItem, lookup?: FoodLookupRequest}
     *
     * @throws QuotaExceededException
     */
    public function start(User $user, string $storeName, string $menuName): array
    {
        $cached = FoodItem::query()
            ->where('user_id', $user->id)
            ->where('store_name', $storeName)
            ->where('menu_name', $menuName)
            ->first();

        if ($cached !== null) {
            return ['status' => 'hit', 'food' => $cached];
        }

        $this->ai->assertWithinQuota($user->id);

        $lookup = DB::transaction(function () use ($user, $storeName, $menuName): FoodLookupRequest {
            $lookup = FoodLookupRequest::query()->create([
                'user_id' => $user->id,
                'barcode' => null,
                'barcode_type' => null,
                'status' => FoodLookupStatus::AiPending,
                'meta' => ['store_name' => $storeName, 'menu_name' => $menuName],
                'expires_at' => now()->addDay(),
            ]);

            DB::afterCommit(fn () => EstimateFoodMenuJob::dispatch($lookup->id));

            return $lookup;
        });

        return ['status' => 'ai_pending', 'lookup' => $lookup];
    }
}
