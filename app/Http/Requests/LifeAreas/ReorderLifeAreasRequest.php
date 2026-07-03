<?php

namespace App\Http\Requests\LifeAreas;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderLifeAreasRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['required', 'string', 'ulid'],
        ];
    }
}
