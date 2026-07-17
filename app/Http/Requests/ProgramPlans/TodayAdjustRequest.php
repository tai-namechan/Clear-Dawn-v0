<?php

namespace App\Http\Requests\ProgramPlans;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TodayAdjustRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['execute', 'adjust', 'lighten', 'skip'])],
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
