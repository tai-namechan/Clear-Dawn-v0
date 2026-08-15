<?php

namespace App\Http\Requests\BodyMeasurements;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 体組成PDF取り込みの入力検証。
 */
class ImportBodyMeasurementRequest extends FormRequest
{
    /**
     * 体組成PDF取り込みの認可。
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * 体組成PDF取り込みの入力検証。
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:20480'],
        ];
    }

    /**
     * 体組成PDF取り込みの検証メッセージ。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pdf.required' => '体組成PDFを選択してください。',
            'pdf.mimetypes' => 'PDFファイルを選択してください。',
            'pdf.max' => 'PDFは20MB以下にしてください。',
        ];
    }
}
