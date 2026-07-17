<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;

class ConfigureMoneyImportRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_column' => ['required'],
            'description_column' => ['nullable'],
            'amount_column' => ['nullable'],
            'debit_column' => ['nullable'],
            'credit_column' => ['nullable'],
            'external_id_column' => ['nullable'],
            'date_format' => ['nullable', 'string', 'max:64'],
            'amount_sign' => ['nullable', 'string', 'max:64'],
            'encoding' => ['nullable', 'string', 'max:32'],
            'delimiter' => ['nullable', 'string', 'max:8'],
            'has_header' => ['nullable', 'boolean'],
        ];
    }
}
