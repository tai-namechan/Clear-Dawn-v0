<?php

namespace App\Http\Requests\RoutinePlanSteps;

use App\Enums\StepPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoutinePlanStepRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'routine_item_id' => [
                'required',
                'ulid',
                Rule::exists('routine_items', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'video_id' => [
                'nullable',
                'ulid',
                Rule::exists('videos', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'purpose' => ['nullable', Rule::enum(StepPurpose::class)],
            'target_load' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'load_unit' => ['nullable', 'string', 'max:20'],
            'target_amount' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'amount_unit' => ['nullable', 'string', 'max:20'],
            'target_blocks' => ['nullable', 'integer', 'min:1', 'max:99'],
            'rest_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
