<?php

namespace App\Http\Requests\Today;

use Illuminate\Foundation\Http\FormRequest;

class ShowTodayRequest extends FormRequest
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
        ];
    }
}
