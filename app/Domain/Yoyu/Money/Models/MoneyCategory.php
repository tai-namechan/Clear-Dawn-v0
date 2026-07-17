<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneyCostBehavior;
use App\Domain\Yoyu\Money\Enums\MoneyDirectionScope;
use App\Domain\Yoyu\Money\Enums\MoneyFlexibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'parent_id',
    'name',
    'direction_scope',
    'flexibility_default',
    'cost_behavior_default',
    'is_essential',
    'color',
    'icon',
    'sort_order',
    'is_active',
])]
class MoneyCategory extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_categories';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_essential' => false,
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction_scope' => MoneyDirectionScope::class,
            'flexibility_default' => MoneyFlexibility::class,
            'cost_behavior_default' => MoneyCostBehavior::class,
            'is_essential' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MoneyCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MoneyCategory::class, 'parent_id');
    }

    /**
     * @return HasMany<MoneyCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MoneyCategory::class, 'parent_id');
    }

    /**
     * @return HasMany<MoneyCashflow, $this>
     */
    public function cashflows(): HasMany
    {
        return $this->hasMany(MoneyCashflow::class, 'category_id');
    }

    /**
     * @return HasMany<MoneyTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(MoneyTransaction::class, 'category_id');
    }

    /**
     * @return HasMany<MoneyRecurringRule, $this>
     */
    public function recurringRules(): HasMany
    {
        return $this->hasMany(MoneyRecurringRule::class, 'category_id');
    }
}
