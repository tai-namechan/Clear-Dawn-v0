<?php

namespace App\Http\Requests\Goals;

use App\Enums\GoalMetricDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoalMetricRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'metric_id' => [
                'required',
                'ulid',
                Rule::exists('metrics', 'id')->where(
                    fn ($query) => $query->where(
                        fn ($inner) => $inner->whereNull('user_id')->orWhere('user_id', $this->user()?->id),
                    ),
                ),
            ],
            'baseline_value' => ['nullable', 'numeric'],
            'target_value' => ['nullable', 'numeric'],
            'target_low' => ['nullable', 'numeric'],
            'target_high' => ['nullable', 'numeric'],
            'direction' => ['nullable', Rule::enum(GoalMetricDirection::class)],
            'note' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
