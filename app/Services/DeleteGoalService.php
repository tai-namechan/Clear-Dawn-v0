<?php

namespace App\Services;

use App\Models\Goal;
use Illuminate\Support\Facades\DB;

class DeleteGoalService
{
    public function handle(Goal $goal): void
    {
        DB::transaction(function () use ($goal): void {
            $goal->delete();
        });
    }
}
