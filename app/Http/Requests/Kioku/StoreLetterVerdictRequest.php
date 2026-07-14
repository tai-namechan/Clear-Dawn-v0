<?php

namespace App\Http\Requests\Kioku;

use App\Domain\Kioku\Models\KiokuLetterItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLetterVerdictRequest extends FormRequest
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
            'verdict' => ['required', 'string', Rule::in(KiokuLetterItem::VERDICTS)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'verdict.in' => '判定は HIT / SOFT HIT / MISS / 表示すべきでない記憶 のいずれかです。',
            'note.max' => 'メモは500文字以内で入力してください。',
        ];
    }
}
