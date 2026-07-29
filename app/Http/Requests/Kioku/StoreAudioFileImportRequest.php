<?php

namespace App\Http\Requests\Kioku;

use Illuminate\Foundation\Http\FormRequest;

class StoreAudioFileImportRequest extends FormRequest
{
    /**
     * OpenAI Speech-to-Text compatible formats plus video/mp4 containers.
     * Server-detected MIME is the source of truth (see prepareForValidation).
     */
    public const ALLOWED_MIME_TYPES = [
        'audio/mpeg',
        'audio/mp3',
        'audio/mp4',
        'audio/x-m4a',
        'audio/m4a',
        'audio/mpga',
        'audio/wav',
        'audio/x-wav',
        'audio/vnd.wave',
        'audio/webm',
        'video/webm',
        'video/mp4',
        'audio/x-mp4',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null && (bool) config('kioku.audio_import.enabled', false);
    }

    protected function prepareForValidation(): void
    {
        $file = $this->file('audio');
        if ($file === null) {
            return;
        }

        $detected = $file->getMimeType() ?: null;
        if ($detected !== null) {
            $this->merge(['server_detected_mime' => $detected]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) ceil(((int) config('kioku.audio_import.max_bytes')) / 1024);

        return [
            'client_capture_id' => ['required', 'uuid'],
            'audio' => [
                'required',
                'file',
                'max:'.$maxKilobytes,
                'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
            ],
            'server_detected_mime' => ['nullable', 'string', 'in:'.implode(',', self::ALLOWED_MIME_TYPES)],
            // Client hint only — CaptureMemoryService probes duration server-side.
            'duration_ms' => [
                'nullable',
                'integer',
                'min:1',
                'max:'.((int) config('kioku.audio_import.max_duration_ms', 7_200_000)),
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
            'audio.max' => '音声ファイルが上限サイズ（24MB）を超えています。',
            'audio.mimetypes' => 'この音声形式には対応していません。',
        ];
    }
}
