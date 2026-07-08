<?php

namespace App\Queries;

use App\Models\RoutineItem;
use App\Models\User;
use RuntimeException;

class GetRoutineItemQuery
{
    public function handle(User $user, string $routineItemId): RoutineItem
    {
        /** @var RoutineItem|null $routineItem */
        $routineItem = RoutineItem::query()
            ->where('user_id', $user->id)
            ->whereKey($routineItemId)
            ->with(['lifeArea', 'videos'])
            ->withCount('videos')
            ->first();

        if ($routineItem === null) {
            throw new RuntimeException('Routine item not found.');
        }

        return $routineItem;
    }
}
