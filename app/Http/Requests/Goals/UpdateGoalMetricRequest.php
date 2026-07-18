<?php

namespace App\Http\Requests\Goals;

use App\Enums\GoalMetricDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoalMetricRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'baseline_value' => ['sometimes', 'nullable', 'numeric'],
            'target_value' => ['sometimes', 'nullable', 'numeric'],
            'target_low' => ['sometimes', 'nullable', 'numeric'],
            'target_high' => ['sometimes', 'nullable', 'numeric'],
            'direction' => ['sometimes', 'nullable', Rule::enum(GoalMetricDirection::class)],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
