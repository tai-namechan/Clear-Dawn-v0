<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'decision_id',
    'subject_type',
    'subject_id',
    'relation_type',
])]
class MoneyDecisionLink extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_decision_links';

    /**
     * @return BelongsTo<MoneyDecision, $this>
     */
    public function decision(): BelongsTo
    {
        return $this->belongsTo(MoneyDecision::class);
    }
}
