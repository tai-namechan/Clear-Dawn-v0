<?php

namespace App\Http\Requests\Exercises;

use App\Enums\ExerciseCategory;
use App\Enums\TrackingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExerciseRequest extends FormRequest
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
            'category' => ['sometimes', 'required', Rule::enum(ExerciseCategory::class)],
            'tracking_type' => ['sometimes', 'required', Rule::enum(TrackingType::class)],
            'note' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
