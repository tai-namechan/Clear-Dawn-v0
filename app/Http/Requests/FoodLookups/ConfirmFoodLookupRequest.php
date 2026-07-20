<?php

namespace App\Http\Requests\FoodLookups;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmFoodLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * 既存 food_items と同じ境界（StoreFoodItemRequest 準拠）。
     * 負数・異常上限は設計 §13.4 の方針どおり validate で弾く。
     *
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
