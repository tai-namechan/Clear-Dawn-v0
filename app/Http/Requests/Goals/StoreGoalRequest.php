<?php

namespace App\Http\Requests\Goals;

use App\Enums\GoalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoalRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'why' => ['nullable', 'string'],
            'parent_goal_id' => [
                'nullable',
                'ulid',
                Rule::exists('goals', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            'matrix_cell_id' => [
                'nullable',
                'ulid',
                Rule::exists('matrix_cells', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'status' => ['sometimes', Rule::enum(GoalStatus::class)],
            'deadline' => ['nullable', 'date'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
