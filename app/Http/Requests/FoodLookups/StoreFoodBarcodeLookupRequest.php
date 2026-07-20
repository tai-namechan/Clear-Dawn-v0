<?php

namespace App\Http\Requests\FoodLookups;

use Illuminate\Foundation\Http\FormRequest;

class StoreFoodBarcodeLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * 桁数・チェックディジットの厳密検証は BarcodeNormalizer が行う。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:20'],
        ];
    }
}
