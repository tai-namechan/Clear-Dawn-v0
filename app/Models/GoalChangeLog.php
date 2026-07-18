<?php

namespace App\Models;

use Database\Factories\GoalChangeLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 目標の変更履歴（追記のみ・更新削除しない）。
 *
 * @property string $id
 * @property string $goal_id
 * @property array<string, mixed> $changes
 * @property string|null $reason
 */
#[Fillable(['goal_id', 'changes', 'reason'])]
class GoalChangeLog extends Model
{
    /** @use HasFactory<GoalChangeLogFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }
}
