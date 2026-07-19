<script setup lang="ts">
import { watch } from 'vue';
import AuthLoginForm from '@/components/auth/AuthLoginForm.vue';
import AuthRegisterForm from '@/components/auth/AuthRegisterForm.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type AuthModalMode = 'login' | 'register';

interface Props {
    open: boolean;
    mode: AuthModalMode;
    canResetPassword: boolean;
    canRegister: boolean;
    passwordRules: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:mode', value: AuthModalMode): void;
}>();

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            emit('update:mode', 'login');
        }
    },
);

function switchToRegister(): void {
    emit('update:mode', 'register');
}

function switchToLogin(): void {
    emit('update:mode', 'login');
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="overflow-hidden border-0 bg-transparent p-0 shadow-none sm:max-w-md"
        >
            <div
                class="cd-auth-modal-bg relative overflow-hidden rounded-2xl border border-cd-line/80"
            >
                <div
                    class="absolute inset-0 bg-cd-surface/82 backdrop-blur-[2px]"
                    aria-hidden="true"
                />

                <div class="relative p-6 md:p-8">
                    <DialogHeader class="mb-6 space-y-2 text-center">
                        <DialogTitle
                            class="font-serif text-2xl font-normal tracking-[0.12em] text-cd-dawn-deep"
                        >
                            {{ mode === 'login' ? 'ログイン' : '新規登録' }}
                        </DialogTitle>
                        <div
                            aria-hidden="true"
                            class="cd-mask-ornament mx-auto h-4 w-32 text-cd-gilt"
                        />
                        <DialogDescription class="sr-only">
                            {{
                                mode === 'login'
                                    ? 'メールアドレスとパスワードでログインします'
                                    : '新しいアカウントを作成します'
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <AuthLoginForm
                        v-if="mode === 'login'"
                        :key="'login'"
                        modal
                        :can-reset-password="canResetPassword"
                        :can-register="canRegister"
                        @switch-to-register="switchToRegister"
                    />
                    <AuthRegisterForm
                        v-else-if="canRegister"
                        :key="'register'"
                        modal
                        :password-rules="passwordRules"
                        @switch-to-login="switchToLogin"
                    />
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
