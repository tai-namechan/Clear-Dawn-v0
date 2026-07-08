<?php

namespace App\Queries;

use App\Models\Routine;
use App\Models\User;
use RuntimeException;

class GetRoutineEditorQuery
{
    public function handle(User $user, string $routineId): Routine
    {
        /** @var Routine|null $routine */
        $routine = Routine::query()
            ->where('user_id', $user->id)
            ->whereKey($routineId)
            ->with([
                'lifeArea',
                'routineSteps' => fn ($query) => $query->orderBy('sort_order'),
                'routineSteps.exercise',
                'routineSteps.video',
            ])
            ->first();

        if ($routine === null) {
            throw new RuntimeException('Routine not found.');
        }

        return $routine;
    }
}
