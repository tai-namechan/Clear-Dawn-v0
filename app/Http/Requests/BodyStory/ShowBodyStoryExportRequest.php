<?php

namespace App\Http\Requests\BodyStory;

use App\Enums\BodyStoryKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowBodyStoryExportRequest extends FormRequest
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
            'date' => ['sometimes', 'date'],
            'kind' => ['required', 'string', Rule::in(BodyStoryKind::values())],
        ];
    }
}
