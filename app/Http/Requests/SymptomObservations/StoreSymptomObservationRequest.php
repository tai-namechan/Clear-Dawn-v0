<?php

namespace App\Http\Requests\SymptomObservations;

use Illuminate\Foundation\Http\FormRequest;

class StoreSymptomObservationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'observed_on' => ['sometimes', 'date'],
            'body_region' => ['required', 'string', 'max:100'],
            'symptom_kind' => ['required', 'string', 'max:100'],
            'severity' => ['required', 'integer', 'min:0', 'max:10'],
            'is_new' => ['sometimes', 'boolean'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
