<?php

namespace App\Http\Requests\NutritionGoals;

use Illuminate\Foundation\Http\FormRequest;

class UpsertNutritionGoalsRequest extends FormRequest
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
            'kcal' => ['required', 'numeric', 'min:0', 'max:20000'],
            'protein_g' => ['required', 'numeric', 'min:0', 'max:999'],
            'fat_g' => ['required', 'numeric', 'min:0', 'max:999'],
            'carb_g' => ['required', 'numeric', 'min:0', 'max:999'],
        ];
    }
}
