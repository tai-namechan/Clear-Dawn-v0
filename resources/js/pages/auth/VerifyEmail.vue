<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard, logout } from '@/routes';
import { send } from '@/routes/verification';

/**
 * Email verification gate (docs/product/signup-policy.md). The link opens in
 * the device browser, so the iOS app needs a way back in once it is done.
 */
defineOptions({
    layout: {
        title: 'メールを確認してください',
        description:
            '登録したメールアドレスに確認用のリンクを送りました。リンクを開くと使い始められます。',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="メールの確認" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-700"
        role="status"
    >
        確認用のリンクをもう一度送りました。
    </div>

    <div class="space-y-4 text-center">
        <Button as-child class="w-full">
            <Link :href="dashboard()">確認が済んだら開く</Link>
        </Button>

        <Form v-bind="send.form()" v-slot="{ processing }">
            <Button :disabled="processing" variant="secondary" class="w-full">
                <Spinner v-if="processing" />
                確認メールをもう一度送る
            </Button>
        </Form>

        <p class="text-xs text-[var(--cd-auth-muted)]">
            届かないときは迷惑メールのフォルダも確認してください。
        </p>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            ログアウト
        </TextLink>
    </div>
</template>
