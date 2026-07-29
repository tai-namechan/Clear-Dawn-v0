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

    /*
    |--------------------------------------------------------------------------
    | Feature flags (default OFF — safe to merge)
    |--------------------------------------------------------------------------
    */

    'audio_import' => [
        'enabled' => (bool) env('KIOKU_AUDIO_IMPORT_ENABLED', false),
        'max_bytes' => (int) env('KIOKU_AUDIO_IMPORT_MAX_MB', 24) * 1024 * 1024,
    ],

    'embedding' => [
        'enabled' => (bool) env('KIOKU_EMBEDDING_ENABLED', false),
        'provider' => env('KIOKU_EMBEDDING_PROVIDER', 'openai'),
        'model' => env('KIOKU_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('KIOKU_EMBEDDING_DIMENSIONS', 1536),
        'schema_version' => env('KIOKU_EMBEDDING_SCHEMA_VERSION', 'v1'),
        'batch_size' => (int) env('KIOKU_EMBEDDING_BATCH_SIZE', 50),
        'max_memories_per_user' => (int) env('KIOKU_EMBEDDING_MAX_MEMORIES_PER_USER', 1000),
        'max_document_chars' => (int) env('KIOKU_EMBEDDING_MAX_DOCUMENT_CHARS', 8000),
        'api_key' => env('OPENAI_EMBEDDING_API_KEY'),
    ],

    'semantic_search' => [
        'enabled' => (bool) env('KIOKU_SEMANTIC_SEARCH_ENABLED', false),
        'top_k' => (int) env('KIOKU_SEMANTIC_SEARCH_TOP_K', 40),
        'recall_limit' => (int) env('KIOKU_SEMANTIC_RECALL_LIMIT', 4),
    ],

    'recall_feedback' => [
        'enabled' => (bool) env('KIOKU_RECALL_FEEDBACK_ENABLED', false),
    ],

    'ios_shortcut' => [
        'enabled' => (bool) env('KIOKU_IOS_SHORTCUT_ENABLED', false),
        // Alias for plan naming
        'capture_enabled' => (bool) env('KIOKU_IOS_CAPTURE_ENABLED', env('KIOKU_IOS_SHORTCUT_ENABLED', false)),
    ],

    'obsidian_export' => [
        'enabled' => (bool) env('KIOKU_OBSIDIAN_EXPORT_ENABLED', false),
    ],

    'action_export' => [
        'enabled' => (bool) env('KIOKU_ACTION_EXPORT_ENABLED', false),
    ],

];
