<?php

namespace App\Models;

use App\Enums\FoodLookupStatus;
use Database\Factories\FoodLookupRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * バーコード/成分表照合の非同期リクエスト（PR-F1/F2 共有・設計 §13.2）。
 *
 * @property string $id
 * @property int $user_id
 * @property string $barcode 正規化済み（UPC-A は EAN-13 に0埋め）
 * @property string $barcode_type ean8 / upca / ean13
 * @property FoodLookupStatus $status
 * @property string|null $source openfoodfacts 等
 * @property array<string, mixed>|null $result
 * @property string|null $error_code
 * @property string|null $temp_image_path
 * @property Carbon|null $expires_at
 */
#[Fillable([
    'user_id',
    'barcode',
    'barcode_type',
    'status',
    'source',
    'result',
    'error_code',
    'temp_image_path',
    'expires_at',
])]
class FoodLookupRequest extends Model
{
    /** @use HasFactory<FoodLookupRequestFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FoodLookupStatus::class,
            'result' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
