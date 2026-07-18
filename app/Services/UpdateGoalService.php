<?php

namespace App\Services;

use App\Models\Goal;
use Illuminate\Support\Facades\DB;

/**
 * 目標の更新。変更内容と理由を goal_change_logs に必ず記録する（goals.md）。
 */
class UpdateGoalService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Goal $goal, array $attributes, string $reason): Goal
    {
        return DB::transaction(function () use ($goal, $attributes, $reason): Goal {
            $goal->fill($attributes);

            $changes = [];

            foreach ($goal->getDirty() as $field => $newValue) {
                $changes[$field] = [
                    'from' => $goal->getOriginal($field) instanceof \BackedEnum
                        ? $goal->getOriginal($field)->value
                        : $goal->getOriginal($field),
                    'to' => $newValue instanceof \BackedEnum ? $newValue->value : $newValue,
                ];
            }

            if ($changes !== []) {
                $goal->save();
                $goal->changeLogs()->create([
                    'changes' => $changes,
                    'reason' => $reason,
                ]);
            }

            return $goal;
        });
    }
}
