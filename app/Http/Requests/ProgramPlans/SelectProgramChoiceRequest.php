<?php

namespace App\Http\Requests\ProgramPlans;

use Illuminate\Foundation\Http\FormRequest;

class SelectProgramChoiceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date'],
            'choice_option_id' => ['required', 'ulid', 'exists:program_choice_options,id'],
        ];
    }
}
