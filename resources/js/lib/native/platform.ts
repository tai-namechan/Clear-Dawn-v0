import { Capacitor } from '@capacitor/core';

/**
 * True only inside the Clear Dawn iOS app (Capacitor WebView).
 * In a normal browser every native helper falls back to the web behaviour.
 */
export function isNativeApp(): boolean {
    return Capacitor.isNativePlatform();
}
