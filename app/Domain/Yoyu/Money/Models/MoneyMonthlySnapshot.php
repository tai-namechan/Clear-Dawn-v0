<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'year_month',
    'revision',
    'status',
    'formula_version',
    'as_of_date',
    'currency_code',
    'balances_payload',
    'cashflows_payload',
    'margin_payload',
    'assumptions_payload',
    'note',
    'closed_at',
    'supersedes_id',
])]
class MoneyMonthlySnapshot extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_monthly_snapshots';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'revision' => 1,
        'formula_version' => '1',
        'currency_code' => 'JPY',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'as_of_date' => 'date',
            'balances_payload' => 'array',
            'cashflows_payload' => 'array',
            'margin_payload' => 'array',
            'assumptions_payload' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MoneyMonthlySnapshot, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(MoneyMonthlySnapshot::class, 'supersedes_id');
    }
}
