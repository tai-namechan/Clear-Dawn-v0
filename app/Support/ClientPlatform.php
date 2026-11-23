<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Tells the Clear Dawn iOS app (Capacitor WebView) apart from a browser.
 * The app appends this marker to its User-Agent (capacitor.config.ts).
 */
class ClientPlatform
{
    public const APP_USER_AGENT_MARKER = 'ClearDawnApp';

    public static function isNativeApp(Request $request): bool
    {
        return str_contains((string) $request->userAgent(), self::APP_USER_AGENT_MARKER);
    }
}
