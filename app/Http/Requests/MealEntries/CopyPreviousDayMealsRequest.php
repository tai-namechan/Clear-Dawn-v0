<?php

namespace App\Http\Requests\MealEntries;

use Illuminate\Foundation\Http\FormRequest;

class CopyPreviousDayMealsRequest extends FormRequest
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
            'date' => ['required', 'date'],
        ];
    }
}
