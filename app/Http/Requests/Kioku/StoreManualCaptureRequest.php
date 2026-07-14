<?php

namespace App\Http\Requests\Kioku;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualCaptureRequest extends FormRequest
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
            'client_capture_id' => ['required', 'uuid'],
            'raw_content' => ['required', 'string', 'max:20000'],
            'captured_at' => ['nullable', 'date'],
            'sensitive' => ['nullable', 'boolean'],
        ];
    }
}
