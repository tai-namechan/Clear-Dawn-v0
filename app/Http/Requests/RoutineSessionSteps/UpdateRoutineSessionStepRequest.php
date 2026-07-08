<?php

namespace App\Http\Requests\RoutineSessionSteps;

use App\Enums\RoutineSessionStepStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoutineSessionStepRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(RoutineSessionStepStatus::class)],
            'actual_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:86400'],
            'memo' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
