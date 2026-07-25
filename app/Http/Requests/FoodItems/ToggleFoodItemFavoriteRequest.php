<?php

namespace App\Http\Requests\FoodItems;

use Illuminate\Foundation\Http\FormRequest;

class ToggleFoodItemFavoriteRequest extends FormRequest
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
            'is_favorite' => ['required', 'boolean'],
        ];
    }
}
