<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'パスワードの確認',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="パスワードの確認" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-lg flex-1 flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <Heading
                    title="パスワードの確認"
                    description="続行する前に、パスワードを確認してください。"
                />

                <Link
                    :href="dashboard()"
                    class="mt-2 flex shrink-0 items-center gap-2 rounded-full border border-cd-line/80 bg-white/60 px-3.5 py-1.5 font-sans text-sm text-cd-ink-muted transition-colors hover:border-cd-line hover:text-cd-ink"
                >
                    <ArrowLeft
                        :size="16"
                        :stroke-width="1.6"
                        aria-hidden="true"
                    />
                    ダッシュボードへ戻る
                </Link>
            </div>

            <div class="cd-card p-6 md:p-8">
                <PasskeyVerify
                    :routes="{
                        options: confirmOptions(),
                        submit: confirmStore(),
                    }"
                    label="パスキーで確認する"
                    loading-label="確認中..."
                    separator="またはパスワードで確認"
                />

                <Form
                    v-bind="store.form()"
                    reset-on-success
                    v-slot="{ errors, processing }"
                    class="mt-6"
                >
                    <div class="space-y-6">
                        <div class="grid gap-2">
                            <Label htmlFor="password">パスワード</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                class="mt-1 block w-full"
                                required
                                autocomplete="current-password"
                                autofocus
                                placeholder="パスワード"
                            />

                            <InputError :message="errors.password" />
                        </div>

                        <div class="flex items-center">
                            <Button
                                class="w-full font-sans tracking-[0.08em]"
                                :disabled="processing"
                                data-test="confirm-password-button"
                            >
                                <Spinner v-if="processing" />
                                パスワードを確認
                            </Button>
                        </div>
                    </div>
                </Form>
            </div>
        </div>
    </div>
</template>
