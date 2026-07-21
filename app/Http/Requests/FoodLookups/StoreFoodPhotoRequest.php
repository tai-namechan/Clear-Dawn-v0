<?php

namespace App\Http\Requests\FoodLookups;

use Illuminate\Foundation\Http\FormRequest;

class StoreFoodPhotoRequest extends FormRequest
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
            'image' => [
                'required',
                'image',
                'mimes:jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000',
            ],
        ];
    }
}
