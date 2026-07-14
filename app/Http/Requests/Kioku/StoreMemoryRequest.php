<?php

namespace App\Http\Requests\Kioku;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemoryRequest extends FormRequest
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
            'client_capture_id' => ['nullable', 'uuid'],
            'raw_content' => ['required', 'string', 'max:20000'],
            'source_type' => ['nullable', 'string', 'in:manual,url'],
            'sensitive' => ['nullable', 'boolean'],
            'captured_at' => ['nullable', 'date'],
        ];
    }
}
