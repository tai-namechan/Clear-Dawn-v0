<?php

namespace App\Http\Requests\Kioku;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecallFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (bool) config('kioku.recall_feedback.enabled', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search_session_id' => ['required', 'uuid'],
            'query_hash' => ['required', 'string', 'size:64'],
            'memory_id' => ['nullable', 'ulid'],
            'shown_rank' => ['nullable', 'integer', 'min:1', 'max:40'],
            'tag_rank' => ['nullable', 'integer', 'min:1', 'max:100'],
            'fulltext_rank' => ['nullable', 'integer', 'min:1', 'max:100'],
            'vector_rank' => ['nullable', 'integer', 'min:1', 'max:100'],
            'final_score' => ['nullable', 'numeric'],
            'verdict' => ['required', Rule::in(['hit', 'related', 'miss', 'not_found'])],
        ];
    }
}
