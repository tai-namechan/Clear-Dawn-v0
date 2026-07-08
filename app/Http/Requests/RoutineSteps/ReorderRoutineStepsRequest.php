<?php

namespace App\Http\Requests\RoutineSteps;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderRoutineStepsRequest extends FormRequest
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
                Rule::exists('routine_steps', 'id'),
            ],
        ];
    }
}
