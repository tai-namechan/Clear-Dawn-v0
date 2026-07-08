<?php

namespace App\Http\Requests\RoutineBlockLogs;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoutineBlockLogRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'load_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'load_unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'amount_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'amount_unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'memo' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
