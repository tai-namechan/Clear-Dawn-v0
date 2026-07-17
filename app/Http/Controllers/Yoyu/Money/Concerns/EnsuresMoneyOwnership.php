<?php

namespace App\Http\Controllers\Yoyu\Money\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait EnsuresMoneyOwnership
{
    protected function ensureOwned(User $user, Model $model): void
    {
        abort_unless(
            isset($model->user_id) && (int) $model->user_id === (int) $user->id,
            404,
        );
    }
}
