<?php

namespace App\Http\Requests\RoutineSteps;

use App\Enums\StepPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoutineStepRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'exercise_id' => [
                'required',
                'ulid',
                Rule::exists('exercises', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'video_id' => [
                'nullable',
                'ulid',
                Rule::exists('videos', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'purpose' => ['nullable', Rule::enum(StepPurpose::class)],
            'target_sets' => ['nullable', 'integer', 'min:1', 'max:99'],
            'target_reps' => ['nullable', 'integer', 'min:1', 'max:999'],
            'target_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'target_distance_m' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'target_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'rest_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
