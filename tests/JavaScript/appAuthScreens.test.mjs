import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

describe('app-style sign-in screens (docs/product/ios-app.md)', () => {
    it('login and register share the layout tabs', () => {
        assert.match(read('resources/js/pages/auth/Login.vue'), /authTab: 'login'/);
        assert.match(read('resources/js/pages/auth/Register.vue'), /authTab: 'register'/);
        assert.match(read('resources/js/layouts/auth/AuthSimpleLayout.vue'), /cd-auth-tabs/);
        assert.match(read('resources/js/layouts/AuthLayout.vue'), /:auth-tab="authTab"/);
    });

    it('offers Apple only when the server enables it', () => {
        for (const page of ['Login', 'Register']) {
            const source = read(`resources/js/pages/auth/${page}.vue`);

            assert.match(source, /<template v-if="appleSignInEnabled">\s*<AppleSignInButton \/>/, page);
        }
    });

    it('uses the native Apple sheet in the app and the web flow in the browser', () => {
        const button = read('resources/js/components/auth/AppleSignInButton.vue');

        assert.match(button, /signInWithAppleNative/);
        assert.match(button, /\/auth\/apple\/redirect/);
        assert.match(read('resources/js/lib/native/appleSignIn.ts'), /\/auth\/apple\/nonce/);
    });

    it('the app marks its User-Agent so the server opens the sign-in screen', () => {
        assert.match(read('capacitor.config.ts'), /appendUserAgent: 'ClearDawnApp'/);
    });

    it('the iOS target has the Sign in with Apple capability', () => {
        assert.match(read('ios/App/App/App.entitlements'), /com\.apple\.developer\.applesignin/);
        assert.match(read('ios/App/App.xcodeproj/project.pbxproj'), /CODE_SIGN_ENTITLEMENTS = App\/App\.entitlements;/);
    });
});
