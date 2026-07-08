<?php

namespace App\Policies;

use App\Enums\RoutinePlanStatus;
use App\Enums\RoutineSessionStatus;
use App\Models\RoutinePlan;
use App\Models\RoutineSession;
use App\Models\User;

class RoutineSessionPolicy
{
    public function view(User $user, RoutineSession $session): bool
    {
        return $this->owns($user, $session);
    }

    public function start(User $user, RoutinePlan $plan): bool
    {
        return $plan->user_id === $user->id
            && $plan->status === RoutinePlanStatus::Ready
            && $plan->steps()->exists();
    }

    public function record(User $user, RoutineSession $session): bool
    {
        return $this->owns($user, $session) && $session->status === RoutineSessionStatus::InProgress;
    }

    public function complete(User $user, RoutineSession $session): bool
    {
        return $this->owns($user, $session) && in_array($session->status, [
            RoutineSessionStatus::InProgress,
            RoutineSessionStatus::Completed,
        ], true);
    }

    public function abort(User $user, RoutineSession $session): bool
    {
        return $this->owns($user, $session) && $session->status === RoutineSessionStatus::InProgress;
    }

    private function owns(User $user, RoutineSession $session): bool
    {
        return $session->user_id === $user->id;
    }
}
