<?php

namespace App\Http\Requests\Recommendations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideRecommendationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action_key' => ['required_without:recommendation_option_id', 'string', Rule::in(['execute', 'adjust', 'lighten', 'skip', 'detail'])],
            'recommendation_option_id' => ['sometimes', 'nullable', 'ulid'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
