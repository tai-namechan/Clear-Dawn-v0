<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ApiError } from '@/lib/apiFetch';
import { signInWithAppleNative } from '@/lib/native/appleSignIn';
import { isNativeApp } from '@/lib/native/platform';

/**
 * "Apple でサインイン" (Apple's black button style, docs/product/ios-app.md).
 * iOS app: native sheet. Browser: Apple's web sign-in page.
 */
const busy = ref(false);
const error = ref<string | null>(null);

function messageFrom(caught: unknown): string {
    if (caught instanceof ApiError) {
        const body = caught.body as { errors?: { apple?: string[] } } | null;
        const message = body?.errors?.apple?.[0];

        if (message) {
            return message;
        }
    }

    return 'Apple でサインインできませんでした。もう一度お試しください。';
}

async function signIn(): Promise<void> {
    error.value = null;

    if (!isNativeApp()) {
        // Full page navigation: Apple's page is outside the SPA.
        window.location.href = '/auth/apple/redirect';

        return;
    }

    busy.value = true;

    try {
        const redirect = await signInWithAppleNative();

        if (redirect) {
            router.visit(redirect);
        }
    } catch (caught) {
        error.value = messageFrom(caught);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <button
            type="button"
            class="cd-apple-button"
            :disabled="busy"
            @click="signIn"
        >
            <svg
                viewBox="0 0 17 20"
                aria-hidden="true"
                class="h-[18px] w-[16px]"
            >
                <path
                    fill="currentColor"
                    d="M14.1 10.6c0-2.6 2.1-3.8 2.2-3.9-1.2-1.8-3.1-2-3.7-2-1.6-.2-3.1.9-3.9.9-.8 0-2-.9-3.4-.9C3.6 4.7 2 5.8 1.1 7.4c-1.9 3.2-.5 8 1.3 10.6.9 1.3 1.9 2.7 3.3 2.6 1.3-.1 1.8-.8 3.4-.8 1.6 0 2 .8 3.4.8 1.4 0 2.3-1.3 3.2-2.6 1-1.5 1.4-2.9 1.4-3-.1 0-2.9-1.1-2.9-4.4ZM11.5 3c.7-.9 1.2-2.1 1.1-3.3-1 0-2.3.7-3 1.6-.7.8-1.3 2-1.1 3.2 1.2.1 2.3-.6 3-1.5Z"
                />
            </svg>
            {{ busy ? 'サインイン中…' : 'Apple でサインイン' }}
        </button>
        <p
            v-if="error"
            class="text-center font-sans text-sm text-destructive"
            role="alert"
        >
            {{ error }}
        </p>
    </div>
</template>
