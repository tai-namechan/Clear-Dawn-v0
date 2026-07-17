<?php

namespace App\Http\Requests\Yoyu\Money\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait AuthorizesMoneyUser
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function ownedExists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('user_id', $this->user()->id);
    }

    /**
     * @return list<string|ValidationRule>
     */
    protected function nonNegativeMinorRules(bool $required = true): array
    {
        $rules = [$required ? 'required' : 'nullable', 'string', 'regex:/^\d+$/'];

        return $rules;
    }

    /**
     * @return list<string|ValidationRule>
     */
    protected function signedMinorRules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'regex:/^-?\d+$/'];
    }

    /**
     * @return list<string|ValidationRule>
     */
    protected function currencyCodeRules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'in:JPY'];
    }

    /**
     * @return list<string|ValidationRule>
     */
    protected function monthRules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'regex:/^\d{4}-\d{2}$/'];
    }
}
