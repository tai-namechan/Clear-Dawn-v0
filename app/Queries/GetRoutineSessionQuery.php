<?php

namespace App\Queries;

use App\Models\RoutineSession;
use App\Models\User;
use RuntimeException;

class GetRoutineSessionQuery
{
    public function handle(User $user, string $sessionId): RoutineSession
    {
        /** @var RoutineSession|null $session */
        $session = RoutineSession::query()
            ->where('user_id', $user->id)
            ->whereKey($sessionId)
            ->with([
                'routinePlan.lifeArea',
                'steps' => fn ($query) => $query->orderBy('sort_order'),
                'steps.routineItem',
                'steps.video',
                'steps.blockLogs' => fn ($query) => $query->orderBy('block_number'),
            ])
            ->first();

        if ($session === null) {
            throw new RuntimeException('Routine session not found.');
        }

        return $session;
    }
}
