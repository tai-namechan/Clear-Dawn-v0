<?php

namespace App\Http\Requests\RoutinePlanSteps;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderRoutinePlanStepsRequest extends FormRequest
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
                Rule::exists('routine_plan_steps', 'id'),
            ],
        ];
    }
}
