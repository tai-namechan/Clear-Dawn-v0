<?php

namespace App\Http\Requests\TrainingPlanSteps;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderTrainingPlanStepsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => [
                'required',
                'ulid',
                Rule::exists('training_plan_steps', 'id'),
            ],
        ];
    }
}
