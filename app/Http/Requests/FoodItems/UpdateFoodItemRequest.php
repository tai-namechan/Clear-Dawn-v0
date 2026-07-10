<?php

namespace App\Http\Requests\FoodItems;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFoodItemRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:100'],
            'serving_label' => ['required', 'string', 'max:50'],
            'kcal' => ['required', 'numeric', 'min:0', 'max:9999'],
            'protein_g' => ['required', 'numeric', 'min:0', 'max:999'],
            'fat_g' => ['required', 'numeric', 'min:0', 'max:999'],
            'carb_g' => ['required', 'numeric', 'min:0', 'max:999'],
        ];
    }
}
