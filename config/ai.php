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
        // USD per 1M tokens (approximate; used for usage logs)
        'claude-haiku-4-5-20251001' => ['input' => 1.0, 'output' => 5.0],
        'claude-sonnet-4-6' => ['input' => 3.0, 'output' => 15.0],
        'default' => ['input' => 3.0, 'output' => 15.0],
    ],

    'limits' => [
        'monthly_usd_per_user' => (float) env('AI_MONTHLY_USD_PER_USER', 10),
    ],

    'timeout' => (int) env('AI_HTTP_TIMEOUT', 60),
];
