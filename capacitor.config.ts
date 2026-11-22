import type { CapacitorConfig } from '@capacitor/cli';
import { resolveCapacitorServerUrl } from './scripts/lib/capacitorServer.mjs';

/**
 * Clear Dawn iOS app (docs/product/ios-app.md).
 * The WebView loads the Laravel server set in CAP_SERVER_URL; the bundled
 * `capacitor/www` only holds the launch fallback and the offline page.
 */
const server = resolveCapacitorServerUrl(process.env.CAP_SERVER_URL);

const config: CapacitorConfig = {
    appId: 'jp.seedk.cleardawn',
    appName: 'Clear Dawn',
    webDir: 'capacitor/www',
    backgroundColor: '#FBF7EF',
    // The server opens the sign-in screen instead of the landing page for this marker (App\Support\ClientPlatform).
    appendUserAgent: 'ClearDawnApp',
    server: {
        ...(server ? { url: server.url, cleartext: server.cleartext } : {}),
        errorPath: 'offline.html',
    },
    ios: {
        contentInset: 'never',
        scheme: 'Clear Dawn',
    },
};

export default config;
