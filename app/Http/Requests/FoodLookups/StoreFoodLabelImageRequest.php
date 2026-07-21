<?php

namespace App\Http\Requests\FoodLookups;

use Illuminate\Foundation\Http\FormRequest;

class StoreFoodLabelImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Anthropic vision の上限（≦5MB・≦8000px）を validate 時点で保証し、
     * Job 側の失敗要因を消す（設計 §13.4 手順3）。
     *
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
