<?php

namespace App\Queries;

use App\Models\TrainingRun;
use App\Models\User;
use RuntimeException;

class GetTrainingRunQuery
{
    public function handle(User $user, string $runId): TrainingRun
    {
        /** @var TrainingRun|null $run */
        $run = TrainingRun::query()
            ->where('user_id', $user->id)
            ->whereKey($runId)
            ->with([
                'trainingPlan.lifeArea',
                'steps' => fn ($query) => $query->orderBy('sort_order'),
                'steps.exercise',
                'steps.video',
                'steps.setLogs' => fn ($query) => $query->orderBy('set_number'),
            ])
            ->first();

        if ($run === null) {
            throw new RuntimeException('Training run not found.');
        }

        return $run;
    }
}
