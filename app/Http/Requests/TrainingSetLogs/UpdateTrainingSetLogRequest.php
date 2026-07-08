<?php

namespace App\Http\Requests\TrainingSetLogs;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrainingSetLogRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'reps' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999'],
            'distance_m' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:86400'],
            'memo' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
