<?php

namespace App\Http\Requests\Yoyu;

use App\Domain\Yoyu\Support\PlaceNameNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpsertYoyuPlaceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'travel_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'external_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $name = (string) $this->input('name', '');

            if (PlaceNameNormalizer::normalize($name) === '') {
                $validator->errors()->add('name', '場所名を入力してください。');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => '場所名を入力してください。',
            'travel_minutes.required' => '移動時間（分）を入力してください。',
            'travel_minutes.min' => '移動時間は0分以上にしてください。',
            'travel_minutes.max' => '移動時間は480分以下にしてください。',
        ];
    }
}
