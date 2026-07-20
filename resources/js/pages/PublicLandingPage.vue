<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthModal from '@/components/auth/AuthModal.vue';
import type { AuthModalMode } from '@/components/auth/AuthModal.vue';
import TopHeroSection from '@/components/landing/TopHeroSection.vue';

interface Props {
    canResetPassword: boolean;
    canRegister: boolean;
    passwordRules: string;
}

defineProps<Props>();

const authModalOpen = ref(false);
const authModalMode = ref<AuthModalMode>('login');

function openAuthModal(mode: AuthModalMode): void {
    authModalMode.value = mode;
    authModalOpen.value = true;
}
</script>

<template>
    <Head title="Clear Dawn" />

    <TopHeroSection
        :can-register="canRegister"
        @open-login="openAuthModal('login')"
        @open-register="openAuthModal('register')"
    />

    <AuthModal
        v-model:open="authModalOpen"
        v-model:mode="authModalMode"
        :can-reset-password="canResetPassword"
        :can-register="canRegister"
        :password-rules="passwordRules"
    />
</template>
