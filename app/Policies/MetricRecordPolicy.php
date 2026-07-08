<?php

namespace App\Policies;

use App\Models\MetricRecord;
use App\Models\User;

class MetricRecordPolicy
{
    public function view(User $user, MetricRecord $record): bool
    {
        return $this->owns($user, $record);
    }

    public function update(User $user, MetricRecord $record): bool
    {
        return $this->owns($user, $record);
    }

    public function delete(User $user, MetricRecord $record): bool
    {
        return $this->owns($user, $record);
    }

    private function owns(User $user, MetricRecord $record): bool
    {
        return $record->user_id === $user->id;
    }
}
