<?php

namespace App\Http\Requests\DailyCheckins;

use Illuminate\Foundation\Http\FormRequest;

class UpsertDailyCheckinRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'checked_on' => ['sometimes', 'date'],
            'sleep_quality' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'fatigue' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'muscle_soreness' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'stress' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'mood' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'region_tension' => ['sometimes', 'nullable', 'array'],
            'region_tension.*' => ['integer', 'min:0', 'max:10'],
            'readiness_self' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
