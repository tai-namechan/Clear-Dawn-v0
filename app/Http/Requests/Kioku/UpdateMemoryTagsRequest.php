<?php

namespace App\Http\Requests\Kioku;

use App\Domain\Kioku\Services\KiokuTagNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMemoryTagsRequest extends FormRequest
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
            // 'present' allows clearing every tag with an empty array.
            'tags' => ['present', 'array', 'max:'.KiokuTagNormalizer::MAX_TAGS],
            'tags.*' => ['string', 'max:'.KiokuTagNormalizer::MAX_TAG_CHARS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tags.max' => 'タグは最大'.KiokuTagNormalizer::MAX_TAGS.'件までです。',
            'tags.*.max' => 'タグは'.KiokuTagNormalizer::MAX_TAG_CHARS.'文字以内で入力してください。',
        ];
    }
}
