<?php

return [
    'provider' => env('AI_PROVIDER', 'anthropic'),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],

    'models' => [
        'cheap' => env('AI_MODEL_CHEAP', 'claude-haiku-4-5-20251001'),
        'strong' => env('AI_MODEL_STRONG', 'claude-sonnet-4-6'),
    ],

    'pricing' => [
        // USD per 1M tokens (approximate; used for usage logs).
        // Keys must match the model ID sent to the API / stored in ai_usage_logs.
        // Alias IDs (without date suffix) are included so AI_MODEL_* env values resolve correctly.
        'claude-haiku-4-5' => ['input' => 1.0, 'output' => 5.0],
        'claude-haiku-4-5-20251001' => ['input' => 1.0, 'output' => 5.0],
        // Sonnet 5: use standard $3/$15 (not the $2/$10 intro rate through 2026-08-31).
        // Intro pricing would under-count Anthropic spend after 2026-09-01 if left as a fixed value.
        // During the intro window this over-counts slightly, which is safer for per-user quota.
        'claude-sonnet-5' => ['input' => 3.0, 'output' => 15.0],
        'claude-sonnet-4-6' => ['input' => 3.0, 'output' => 15.0],
        'default' => ['input' => 3.0, 'output' => 15.0],
    ],

    'limits' => [
        // Stored/compared as decimal strings via AiMoney; cast kept for env parsing.
        'monthly_usd_per_user' => env('AI_MONTHLY_USD_PER_USER', '10'),
    ],

    'timeout' => (int) env('AI_HTTP_TIMEOUT', 60),
    'connect_timeout' => (int) env('AI_HTTP_CONNECT_TIMEOUT', 10),

    'warnings' => [
        'usage_ratio' => '0.80',
    ],
];
