<?php

namespace App\Policies;

use App\Models\RoutineBlockLog;
use App\Models\RoutineSession;
use App\Models\User;

class RoutineBlockLogPolicy
{
    public function update(User $user, RoutineBlockLog $blockLog): bool
    {
        return $this->canModify($user, $blockLog);
    }

    public function delete(User $user, RoutineBlockLog $blockLog): bool
    {
        return $this->canModify($user, $blockLog);
    }

    private function canModify(User $user, RoutineBlockLog $blockLog): bool
    {
        /** @var RoutineSession $session */
        $session = $blockLog->routineSessionStep->routineSession;

        return $session->user_id === $user->id;
    }
}
