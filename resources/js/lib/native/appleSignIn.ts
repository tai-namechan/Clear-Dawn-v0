import { SignInWithApple } from '@capacitor-community/apple-sign-in';
import { apiFetch } from '@/lib/apiFetch';
import { isNativeApp } from '@/lib/native/platform';
import { isUserCancellation } from '@/lib/nativeMedia.mjs';

/**
 * Sign in with Apple from the iOS app (docs/product/ios-app.md).
 * The server issues a single-use nonce, Apple signs it into the identity
 * token, and the server verifies the token before logging in.
 *
 * Returns the URL to open after signing in, or null when the user cancels.
 */
export async function signInWithAppleNative(): Promise<string | null> {
    if (!isNativeApp()) {
        return null;
    }

    const { nonce } = await apiFetch<{ nonce: string }>('/auth/apple/nonce');

    let response;

    try {
        response = await SignInWithApple.authorize({
            // Native requests are validated against the bundle ID on the server.
            clientId: 'com.cleardawn.app',
            redirectURI: `${window.location.origin}/auth/apple/callback`,
            scopes: 'email name',
            nonce,
        });
    } catch (error) {
        if (isUserCancellation(error) || String(error).includes('1001')) {
            return null;
        }

        throw error;
    }

    const { redirect } = await apiFetch<{ redirect: string }>(
        '/auth/apple/native',
        {
            method: 'POST',
            body: JSON.stringify({
                identity_token: response.response.identityToken,
                given_name: response.response.givenName,
                family_name: response.response.familyName,
            }),
        },
    );

    return redirect;
}
