<?php

namespace App\Http\Requests\Kioku;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoiceCaptureRequest extends FormRequest
{
    /**
     * Server-detected MIME types MediaRecorder realistically produces
     * (Chrome: webm/opus, Safari: mp4/aac) plus wav/ogg/mp3 fallbacks.
     */
    private const ALLOWED_MIME_TYPES = [
        'audio/webm',
        'video/webm',
        'audio/mp4',
        'video/mp4',
        'audio/x-m4a',
        'audio/aac',
        'audio/mpeg',
        'audio/mp3',
        'audio/ogg',
        'application/ogg',
        'audio/wav',
        'audio/x-wav',
        'audio/vnd.wave',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) ceil(((int) config('kioku.audio.max_bytes')) / 1024);

        return [
            'client_capture_id' => ['required', 'uuid'],
            'audio' => [
                'required',
                'file',
                'max:'.$maxKilobytes,
                'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
            ],
            'duration_ms' => [
                'required',
                'integer',
                'min:1',
                'max:'.((int) config('kioku.audio.max_duration_ms')),
            ],
            'captured_at' => ['nullable', 'date'],
            'sensitive' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audio.max' => '音声ファイルが上限サイズを超えています。',
            'audio.mimetypes' => 'この音声形式には対応していません。',
            'duration_ms.max' => '送信された録音時間が上限（3分）を超えています。',
        ];
    }
}
