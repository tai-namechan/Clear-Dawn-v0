<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthLoginForm from '@/components/auth/AuthLoginForm.vue';

defineOptions({
    layout: {
        title: 'ログイン',
        description: 'メールアドレスとパスワードを入力してログインしてください',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="ログイン" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-cd-moss"
    >
        {{ status }}
    </div>

    <AuthLoginForm :can-reset-password="canResetPassword" :can-register="canRegister" />
</template>
