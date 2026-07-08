<?php

namespace App\Queries;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GetExercisesQuery
{
    /**
     * @return Collection<int, Exercise>
     */
    public function handle(User $user, ?bool $activeOnly = true): Collection
    {
        return Exercise::query()
            ->where('user_id', $user->id)
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->with('lifeArea')
            ->withCount('videos')
            ->orderBy('name')
            ->get();
    }
}
