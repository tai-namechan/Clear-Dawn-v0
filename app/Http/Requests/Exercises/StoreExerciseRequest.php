<?php

namespace App\Http\Requests\Exercises;

use App\Enums\ExerciseCategory;
use App\Enums\TrackingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExerciseRequest extends FormRequest
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
            'category' => ['required', Rule::enum(ExerciseCategory::class)],
            'tracking_type' => ['required', Rule::enum(TrackingType::class)],
            'note' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
