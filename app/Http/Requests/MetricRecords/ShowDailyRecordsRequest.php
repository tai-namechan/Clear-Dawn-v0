<?php

namespace App\Http\Requests\MetricRecords;

use Illuminate\Foundation\Http\FormRequest;

class ShowDailyRecordsRequest extends FormRequest
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
