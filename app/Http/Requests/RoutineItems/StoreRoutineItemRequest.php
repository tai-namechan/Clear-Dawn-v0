<?php

namespace App\Http\Requests\RoutineItems;

use App\Enums\RoutineItemCategory;
use App\Enums\TrackingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoutineItemRequest extends FormRequest
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
            'category' => ['required', Rule::enum(RoutineItemCategory::class)],
            'tracking_type' => ['required', Rule::enum(TrackingType::class)],
            'default_load_unit' => ['nullable', 'string', 'max:20'],
            'default_amount_unit' => ['nullable', 'string', 'max:20'],
            'default_video_id' => [
                'nullable',
                'ulid',
                Rule::exists('videos', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            'note' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
