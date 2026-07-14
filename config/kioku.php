<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Voice capture audio storage
    |--------------------------------------------------------------------------
    |
    | Audio originals are the canonical raw for voice memories and must live
    | on a private disk (no public URLs). In production set
    | KIOKU_AUDIO_DISK=kioku-audio (Laravel Cloud Object Storage disk name).
    | Never use ephemeral local disk as the production persistence target.
    |
    */

    'audio' => [
        'disk' => env('KIOKU_AUDIO_DISK', 'local'),
        'max_bytes' => (int) env('KIOKU_AUDIO_MAX_BYTES', 20 * 1024 * 1024),
        'max_duration_ms' => (int) env('KIOKU_AUDIO_MAX_DURATION_MS', 180_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transcription provider
    |--------------------------------------------------------------------------
    |
    | 'none' (default): audio is stored but transcription stays pending and
    | the UI reports it as not configured. Real providers plug in behind
    | App\Domain\Kioku\Transcription\TranscriptionGateway.
    |
    */

    'transcription' => [
        'provider' => env('KIOKU_TRANSCRIPTION_PROVIDER', 'none'),
    ],

];
