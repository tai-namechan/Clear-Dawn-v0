<?php

namespace App\Http\Requests\RoutineSteps;

use App\Enums\StepPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoutineStepRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'routine_item_id' => [
                'sometimes',
                'required',
                'ulid',
                Rule::exists('routine_items', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'title' => ['sometimes', 'nullable', 'string', 'max:100'],
            'video_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('videos', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'purpose' => ['sometimes', 'nullable', Rule::enum(StepPurpose::class)],
            'target_load' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'load_unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'target_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'amount_unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'target_blocks' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'rest_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3600'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
