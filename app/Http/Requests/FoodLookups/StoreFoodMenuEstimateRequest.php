<?php

namespace App\Http\Requests\FoodLookups;

use Illuminate\Foundation\Http\FormRequest;

class StoreFoodMenuEstimateRequest extends FormRequest
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
            'store_name' => ['required', 'string', 'max:100'],
            'menu_name' => ['required', 'string', 'max:100'],
        ];
    }
}
