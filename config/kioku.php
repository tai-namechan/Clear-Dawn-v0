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
    | App\Domain\Kioku\Transcription\TranscriptionGateway. 'openai' uses the
    | Audio Transcriptions API (docs/product/kioku-final-remaining-
    | implementation.md §2–3); it needs OPENAI_API_KEY and must be enabled
    | per environment — never default to a real provider in code.
    |
    */

    'transcription' => [
        'provider' => env('KIOKU_TRANSCRIPTION_PROVIDER', 'none'),
        'model' => env(
            'KIOKU_TRANSCRIPTION_MODEL',
            'gpt-4o-mini-transcribe-2025-12-15',
        ),
        'language' => env('KIOKU_TRANSCRIPTION_LANGUAGE', 'ja'),
        'timeout_seconds' => (int) env('KIOKU_TRANSCRIPTION_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Concierge letter experiment
    |--------------------------------------------------------------------------
    |
    | Letters (docs/product/kioku-final-remaining-implementation.md Phase B +
    | docs/product/kioku-concierge-daily-pilot.md). Live daily pilot uses the
    | scheduler dispatcher; weekly steady-state stays on the manual
    | kioku:letters:generate command. Delivery is in-app only — no email/push.
    |
    */

    'concierge' => [
        'enabled' => (bool) env('KIOKU_CONCIERGE_ENABLED', false),
        'default_character' => env('KIOKU_CONCIERGE_DEFAULT_CHARACTER', 'shiori'),
        'test_enabled' => (bool) env('KIOKU_CONCIERGE_TEST_ENABLED', false),
    ],

];
