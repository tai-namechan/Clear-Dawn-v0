<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

interface Props {
    canResetPassword: boolean;
    canRegister: boolean;
    /** モーダル内ではページ遷移せずモード切替 */
    modal?: boolean;
}

withDefaults(defineProps<Props>(), {
    modal: false,
});

const emit = defineEmits<{
    (e: 'switch-to-register'): void;
}>();
</script>

<template>
    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">メールアドレス</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">パスワード</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        パスワードをお忘れですか？
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="パスワード"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>ログイン状態を保持する</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full font-sans tracking-[0.08em]"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                ログインする
            </Button>
        </div>

        <div v-if="canRegister" class="text-center text-sm text-muted-foreground">
            アカウントをお持ちでない方
            <button
                v-if="modal"
                type="button"
                class="text-foreground underline underline-offset-4 transition-colors hover:text-primary"
                :tabindex="5"
                @click="emit('switch-to-register')"
            >
                新規登録
            </button>
            <TextLink v-else :href="register()" :tabindex="5"
                >新規登録</TextLink
            >
        </div>
    </Form>
</template>
