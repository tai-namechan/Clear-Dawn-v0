<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const {
    title = '',
    description = '',
    authTab = undefined,
} = defineProps<{
    title?: string;
    description?: string;
    authTab?: 'login' | 'register';
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :auth-tab="authTab">
        <slot />
    </AuthLayout>
</template>
