<?php

namespace App\Queries;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GetRoutinesQuery
{
    /**
     * @return Collection<int, Routine>
     */
    public function handle(User $user, ?bool $activeOnly = true): Collection
    {
        return Routine::query()
            ->where('user_id', $user->id)
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->with('lifeArea')
            ->withCount('routineSteps')
            ->orderBy('sort_order')
            ->get();
    }
}
