<?php

namespace App\Http\Requests\Routines;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoutineRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'life_area_id' => [
                'nullable',
                'ulid',
                Rule::exists('life_areas', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
