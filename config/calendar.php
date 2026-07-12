<?php

return [
    /*
     * 'auto' resolves the real provider chain (Google cache / empty).
     * 'mock' serves fixture events — honored only in local/testing.
     */
    'driver' => env('YOYU_CALENDAR_DRIVER', 'auto'),

    'sync_ttl_minutes' => (int) env('GOOGLE_CALENDAR_SYNC_TTL_MINUTES', 15),
    'sync_past_days' => (int) env('GOOGLE_CALENDAR_SYNC_PAST_DAYS', 1),
    'sync_future_days' => (int) env('GOOGLE_CALENDAR_SYNC_FUTURE_DAYS', 7),
];
