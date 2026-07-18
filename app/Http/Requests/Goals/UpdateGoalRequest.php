<?php

namespace App\Http\Requests\Goals;

use App\Enums\GoalStatus;
use App\Models\Goal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 目標の変更は理由つき履歴（goal_change_logs）を必ず残す（goals.md）。
 */
class UpdateGoalRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $goal = $this->route('goal');

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'why' => ['sometimes', 'nullable', 'string'],
            'parent_goal_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('goals', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
                Rule::notIn([$goal instanceof Goal ? $goal->id : null]),
            ],
            'matrix_cell_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('matrix_cells', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'status' => ['sometimes', Rule::enum(GoalStatus::class)],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
