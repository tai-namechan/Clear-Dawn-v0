<?php

namespace App\Http\Requests\Yoyu;

use Illuminate\Foundation\Http\FormRequest;

class UpdateYoyuTravelLeadRequest extends FormRequest
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
            'prep_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prep_minutes.required' => '支度時間を入力してください。',
            'buffer_minutes.required' => '余白時間を入力してください。',
        ];
    }
}
