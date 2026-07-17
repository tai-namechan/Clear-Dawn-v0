<?php

namespace App\Queries;

use App\Models\Program;
use App\Models\User;

class GetProgramDetailQuery
{
    public function handle(User $user, string $programId): Program
    {
        return Program::query()
            ->where('user_id', $user->id)
            ->with([
                'goal',
                'versions',
                'activeVersion.phases.weeks',
                'activeVersion.dayTemplates.steps.items.routineItem',
                'activeVersion.dayTemplates.steps.choiceOption',
                'activeVersion.dayTemplates.choiceGroup.options',
                'activeVersion.constraints',
                'activeVersion.attachments',
                'activeVersion.metricTargets.metric',
            ])
            ->findOrFail($programId);
    }
}
