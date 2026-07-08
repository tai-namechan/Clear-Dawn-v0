<?php

namespace App\Http\Requests\RoutineItems;

use App\Enums\RoutineItemCategory;
use App\Enums\TrackingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoutineItemRequest extends FormRequest
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
            'category' => ['sometimes', 'required', Rule::enum(RoutineItemCategory::class)],
            'tracking_type' => ['sometimes', 'required', Rule::enum(TrackingType::class)],
            'default_load_unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'default_amount_unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'note' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
