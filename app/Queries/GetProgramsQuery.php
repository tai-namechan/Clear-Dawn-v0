<?php

namespace App\Queries;

use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GetProgramsQuery
{
    /**
     * @return Collection<int, Program>
     */
    public function handle(User $user): Collection
    {
        return Program::query()
            ->where('user_id', $user->id)
            ->with(['goal', 'activeVersion.weeks', 'activeVersion.dayTemplates'])
            ->orderByDesc('created_at')
            ->get();
    }
}
