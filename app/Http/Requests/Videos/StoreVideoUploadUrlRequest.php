<?php

namespace App\Http\Requests\Videos;

use App\Services\VideoStorageClient;
use App\Support\VideoMimeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoUploadUrlRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'mime_type' => ['required', 'string', Rule::in(VideoMimeType::ALLOWED)],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:'.VideoStorageClient::MaxSizeBytes],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
