<?php

namespace App\Http\Requests\TrainingPlanSteps;

use App\Enums\StepPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrainingPlanStepRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'exercise_id' => [
                'sometimes',
                'required',
                'ulid',
                Rule::exists('exercises', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'video_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('videos', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'purpose' => ['sometimes', 'nullable', Rule::enum(StepPurpose::class)],
            'target_sets' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'target_reps' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
            'target_weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'target_distance_m' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'target_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:86400'],
            'rest_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3600'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
