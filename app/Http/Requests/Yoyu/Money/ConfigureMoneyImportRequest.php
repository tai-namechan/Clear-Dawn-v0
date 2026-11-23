<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigureMoneyImportRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * fgetcsv / setCsvControl が受け付ける1バイト区切り文字のみ。
     *
     * 以前は max:8 で任意文字列を通していたため、2文字以上を送ると
     * PHP 8 の ValueError が未捕捉のまま 500 になっていた
     * （docs/audit/2026-07-26-pre-release-audit.md M-1）。
     *
     * @var list<string>
     */
    private const ALLOWED_DELIMITERS = [',', ';', "\t", '|'];

    /**
     * MoneyCsvImportService::decodeCell が実際に扱えるエンコーディング。
     *
     * 未知の値は mb_convert_encoding が ValueError を投げる
     * （@ では抑制できない。PHP 8 では警告ではなく例外のため）。
     *
     * @var list<string>
     */
    private const ALLOWED_ENCODINGS = [
        'UTF-8', 'UTF8',
        'SJIS', 'SHIFT_JIS', 'SHIFT-JIS', 'SJIS-WIN', 'CP932',
        'EUC-JP', 'ISO-8859-1', 'ASCII',
    ];

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
            // amount_sign は意図的に許可リスト化しない。MoneyCsvNormalizer は
            // 'income_positive' 以外を expense_positive として安全に既定処理するため
            // クラッシュ経路が無く、UI は 'signed' を送ってくる。ここを絞ると
            // 不具合を直さずに既存 UI を壊すだけになる。
            'amount_sign' => ['nullable', 'string', 'max:64'],
            'encoding' => ['nullable', 'string', Rule::in(self::ALLOWED_ENCODINGS)],
            'delimiter' => ['nullable', 'string', Rule::in(self::ALLOWED_DELIMITERS)],
            'has_header' => ['nullable', 'boolean'],
        ];
    }

    /**
     * 大文字小文字の揺れ（utf-8 / Shift_JIS 等）で弾かれないよう、
     * 検証前にエンコーディング名を正規化する。
     * MoneyCsvImportService 側も strtoupper して比較している。
     */
    protected function prepareForValidation(): void
    {
        $encoding = $this->input('encoding');

        if (is_string($encoding) && $encoding !== '') {
            $this->merge(['encoding' => strtoupper($encoding)]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delimiter.in' => '区切り文字はカンマ・セミコロン・タブ・パイプのいずれかを指定してください。',
            'encoding.in' => 'この文字コードには対応していません。',
        ];
    }
}
