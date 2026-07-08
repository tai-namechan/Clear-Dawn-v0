<?php

namespace App\Http\Requests\RoutinePlans;

use App\Enums\RoutinePlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoutinePlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:100'],
            'scheduled_on' => ['sometimes', 'required', 'date'],
            'life_area_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('life_areas', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'note' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::enum(RoutinePlanStatus::class)],
        ];
    }
}
