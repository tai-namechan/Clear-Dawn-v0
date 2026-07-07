<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

interface Props {
    passwordRules: string;
    /** モーダル内ではページ遷移せずモード切替 */
    modal?: boolean;
}

withDefaults(defineProps<Props>(), {
    modal: false,
});

const emit = defineEmits<{
    (e: 'switch-to-login'): void;
}>();
</script>

<template>
    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">名前</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="お名前"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">メールアドレス</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">パスワード</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="パスワード"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">パスワード（確認）</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="パスワード（確認）"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full font-sans tracking-[0.08em]"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                登録する
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            すでにアカウントをお持ちの方
            <button
                v-if="modal"
                type="button"
                class="text-foreground underline underline-offset-4 transition-colors hover:text-primary"
                :tabindex="6"
                @click="emit('switch-to-login')"
            >
                ログイン
            </button>
            <TextLink
                v-else
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="6"
                >ログイン</TextLink
            >
        </div>
    </Form>
</template>
