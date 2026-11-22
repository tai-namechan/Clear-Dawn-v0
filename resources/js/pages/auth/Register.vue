<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppleSignInButton from '@/components/auth/AppleSignInButton.vue';
import AuthRegisterForm from '@/components/auth/AuthRegisterForm.vue';

defineProps<{
    passwordRules: string;
    appleSignInEnabled?: boolean;
}>();

defineOptions({
    layout: {
        title: 'はじめまして',
        description: 'Apple、またはメールアドレスでアカウントを作成します',
        authTab: 'register',
    },
});
</script>

<template>
    <Head title="新規登録" />

    <template v-if="appleSignInEnabled">
        <AppleSignInButton />
        <div class="cd-auth-divider"><span>または</span></div>
    </template>

    <AuthRegisterForm :password-rules="passwordRules" />
</template>
