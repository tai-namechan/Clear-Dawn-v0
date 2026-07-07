<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthRegisterForm from '@/components/auth/AuthRegisterForm.vue';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: '新規登録',
        description: '必要事項を入力してアカウントを作成してください',
    },
});
</script>

<template>
    <Head title="新規登録" />

    <AuthRegisterForm :password-rules="passwordRules" />
</template>
