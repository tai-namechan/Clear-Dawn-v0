<?php

namespace App\Http\Requests\Routines;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoutineRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'life_area_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('life_areas', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
