<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppleSignInButton from '@/components/auth/AppleSignInButton.vue';
import AuthLoginForm from '@/components/auth/AuthLoginForm.vue';

defineOptions({
    layout: {
        title: 'おかえりなさい',
        description:
            'メールアドレスとパスワード、または Apple でログインします',
        authTab: 'login',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    appleSignInEnabled?: boolean;
}>();

const page = usePage();

/** Errors from Apple's web callback come back as a session error. */
const appleError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.apple,
);
</script>

<template>
    <Head title="ログイン" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-cd-moss"
    >
        {{ status }}
    </div>

    <template v-if="appleSignInEnabled">
        <AppleSignInButton />
        <p
            v-if="appleError"
            class="mt-2 text-center font-sans text-sm text-destructive"
            role="alert"
        >
            {{ appleError }}
        </p>
        <div class="cd-auth-divider"><span>または</span></div>
    </template>

    <AuthLoginForm
        :can-reset-password="canResetPassword"
        :can-register="canRegister"
    />
</template>
