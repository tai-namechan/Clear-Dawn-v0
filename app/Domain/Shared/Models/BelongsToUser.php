<?php

namespace App\Domain\Shared\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Forces user_id scoping on domain models (Kioku / Yoyu).
 *
 * @method static Builder<static> withoutUserScope()
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user', function (Builder $builder): void {
            $userId = Auth::id();

            if ($userId !== null) {
                $builder->where($builder->getModel()->getTable().'.user_id', $userId);
            }
        });

        static::creating(function ($model): void {
            if ($model->user_id === null && Auth::id() !== null) {
                $model->user_id = Auth::id();
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutUserScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('user');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
