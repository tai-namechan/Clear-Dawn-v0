<?php

namespace App\Http\Requests\MealEntries;

use App\Enums\MealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMealEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;
        $hasFoodItem = filled($this->input('food_item_id'));

        return [
            'eaten_on' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(MealType::values())],
            'food_item_id' => [
                'nullable',
                'ulid',
                Rule::exists('food_items', 'id')->where(fn ($query) => $query->where('user_id', $userId)->whereNull('deleted_at')),
            ],
            'name' => [$hasFoodItem ? 'nullable' : 'required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'kcal' => [$hasFoodItem ? 'nullable' : 'required', 'numeric', 'min:0', 'max:9999'],
            'protein_g' => [$hasFoodItem ? 'nullable' : 'required', 'numeric', 'min:0', 'max:999'],
            'fat_g' => [$hasFoodItem ? 'nullable' : 'required', 'numeric', 'min:0', 'max:999'],
            'carb_g' => [$hasFoodItem ? 'nullable' : 'required', 'numeric', 'min:0', 'max:999'],
            'note' => ['nullable', 'string', 'max:500'],
            'register_as_food' => ['sometimes', 'boolean'],
        ];
    }
}
