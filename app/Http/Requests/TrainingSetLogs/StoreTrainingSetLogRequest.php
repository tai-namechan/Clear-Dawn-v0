<?php

namespace App\Http\Requests\TrainingSetLogs;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingSetLogRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'reps' => ['nullable', 'integer', 'min:0', 'max:999'],
            'distance_m' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'memo' => ['nullable', 'string', 'max:500'],
        ];
    }
}
