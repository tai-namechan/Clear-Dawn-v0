<?php

namespace App\Http\Requests\Yoyu;

use Illuminate\Foundation\Http\FormRequest;

class UpdateYoyuEventTravelLeadRequest extends FormRequest
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
            'external_id' => ['required', 'string', 'max:255'],
            'clear' => ['sometimes', 'boolean'],
            'prep_minutes' => ['exclude_if:clear,1', 'exclude_if:clear,true', 'required', 'integer', 'min:0', 'max:120'],
            'buffer_minutes' => ['exclude_if:clear,1', 'exclude_if:clear,true', 'required', 'integer', 'min:0', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'external_id.required' => '予定を指定してください。',
            'prep_minutes.required' => '支度時間を入力してください。',
            'buffer_minutes.required' => '余白時間を入力してください。',
        ];
    }
}
