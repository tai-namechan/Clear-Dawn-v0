<?php

namespace App\Models;

use Database\Factories\RecommendationOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $recommendation_id
 * @property string $action_key
 * @property string $label
 * @property string|null $description
 * @property int $sort_order
 */
#[Fillable([
    'recommendation_id',
    'action_key',
    'label',
    'description',
    'sort_order',
])]
class RecommendationOption extends Model
{
    /** @use HasFactory<RecommendationOptionFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<Recommendation, $this>
     */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }
}
