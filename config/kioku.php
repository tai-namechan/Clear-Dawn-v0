<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Voice capture audio storage
    |--------------------------------------------------------------------------
    |
    | Audio originals are the canonical raw for voice memories and must live
    | on a private disk (no public URLs). In production point KIOKU_AUDIO_DISK
    | at durable private object storage — never at ephemeral local disk.
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
