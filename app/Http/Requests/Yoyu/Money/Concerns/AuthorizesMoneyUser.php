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
     * 金額（minor unit）の最大桁数。
     *
     * 桁数上限が無いと 100 桁の数値文字列も検証を通過し、
     * サービス層の (int) キャストで PHP_INT_MAX に飽和して
     * 残高計算がオーバーフローする（監査 M-5）。
     * 15 桁 = 兆円規模であり、実用上の上限として十分。
     */
    private const MAX_MINOR_DIGITS = 15;

    /**
     * @return list<string|ValidationRule>
     */
    protected function nonNegativeMinorRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'regex:/^\d{1,'.self::MAX_MINOR_DIGITS.'}$/',
        ];
    }

    /**
     * @return list<string|ValidationRule>
     */
    protected function signedMinorRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'regex:/^-?\d{1,'.self::MAX_MINOR_DIGITS.'}$/',
        ];
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
