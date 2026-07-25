<?php

namespace App\Http\Requests\MealEntries;

use App\Enums\MealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopyMealEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'eaten_on' => ['required', 'date'],
            'meal_type' => ['nullable', Rule::in(MealType::values())],
            'quantity' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
