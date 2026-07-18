<?php

namespace App\Http\Requests\RoutineBlockLogs;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoutineBlockLogRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'load_value' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'load_unit' => ['nullable', 'string', 'max:20'],
            'amount_value' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'amount_unit' => ['nullable', 'string', 'max:20'],
            'memo' => ['nullable', 'string', 'max:500'],
            'rpe' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'distance_value' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'side' => ['nullable', 'string', 'max:20'],
            'extra' => ['nullable', 'array'],
        ];
    }
}
