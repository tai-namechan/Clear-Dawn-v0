<?php

namespace App\Http\Requests\TrainingRunSteps;

use App\Enums\TrainingRunStepStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrainingRunStepRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(TrainingRunStepStatus::class)],
            'actual_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:86400'],
            'memo' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
