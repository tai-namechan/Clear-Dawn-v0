<?php

namespace App\Queries;

use App\Models\RoutineItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GetRoutineItemsQuery
{
    /**
     * @return Collection<int, RoutineItem>
     */
    public function handle(User $user, ?bool $activeOnly = true): Collection
    {
        return RoutineItem::query()
            ->where('user_id', $user->id)
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->with('lifeArea')
            ->withCount('videos')
            ->orderBy('name')
            ->get();
    }
}
