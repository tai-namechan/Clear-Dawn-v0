<?php

namespace App\Http\Requests\Kioku;

use App\Domain\Kioku\Models\KiokuCaptureEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaptureEventRequest extends FormRequest
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
            'event' => ['required', 'string', Rule::in(KiokuCaptureEvent::EVENTS)],
            'source_type' => ['required', 'string', 'in:manual,voice'],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
            'retry_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
