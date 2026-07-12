<?php

namespace App\Http\Requests\Yoyu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateYoyuTaskRequest extends FormRequest
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
            'status' => ['sometimes', 'required', 'string', 'in:inbox,planned,doing,done,snoozed,cancelled'],
            'estimate_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:480'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('status') && ! $this->has('estimate_minutes')) {
                $validator->errors()->add('status', 'status または estimate_minutes のいずれかが必要です。');
            }
        });
    }
}
