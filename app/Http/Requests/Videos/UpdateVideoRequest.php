<?php

namespace App\Http\Requests\Videos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVideoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'life_area_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('life_areas', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            'exercise_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('exercises', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
        ];
    }
}
