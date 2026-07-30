<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { destroy, store } from '@/routes/kioku/settings/capture-tokens';

type CaptureTokenRow = {
    id: string;
    name: string;
    token_prefix: string;
    scope: string;
    last_used_at: string | null;
    revoked_at: string | null;
    created_at: string;
};

const props = defineProps<{
    captureTokens?: CaptureTokenRow[];
    iosShortcutEnabled?: boolean;
    plainToken?: string | null;
}>();

const page = usePage();
const flashedToken = computed(
    () =>
        props.plainToken ??
        ((page.props.flash as { kioku_plain_token?: string } | undefined)
            ?.kioku_plain_token ??
            null),
);

defineOptions({
    layout: {
        title: 'キオク',
        subtitle: '設定',
    },
});
</script>

<template>
    <div class="mx-auto max-w-lg space-y-5">
        <Head title="設定 — キオク" />

        <section
            class="rounded-2xl border border-os-line bg-os-kioku-paper p-5 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
        >
            <h2 class="text-sm font-bold text-os-kioku">iOS Shortcut Capture</h2>
            <p class="mt-1 text-[12.5px] leading-relaxed text-os-sub">
                スコープ付きトークンで Shortcut からキオクへ送れます。平文は発行時のみ表示します。
            </p>

            <div
                v-if="!iosShortcutEnabled"
                class="mt-3 rounded-xl border border-dashed border-os-line p-4 text-[12.5px] text-os-sub"
            >
                `KIOKU_IOS_SHORTCUT_ENABLED` が OFF のため無効です。
            </div>

            <template v-else>
                <p
                    v-if="flashedToken"
                    class="mt-3 break-all rounded-xl border border-os-kioku/30 bg-os-kioku-bg p-3 text-[12px] text-os-ink"
                    role="status"
                >
                    新しいトークン（再表示されません）:<br />
                    <code>{{ flashedToken }}</code>
                </p>

                <Form
                    v-bind="store.form()"
                    class="mt-3 flex flex-col gap-2 sm:flex-row"
                >
                    <input
                        name="name"
                        required
                        maxlength="120"
                        placeholder="例: iPhone Shortcut"
                        class="h-10 flex-1 rounded-xl border border-os-line bg-os-kioku-bg px-3 text-[13px]"
                    />
                    <Button
                        type="submit"
                        class="h-10 rounded-xl bg-os-kioku text-white"
                    >
                        トークンを発行
                    </Button>
                </Form>

                <ul class="mt-4 space-y-2">
                    <li
                        v-for="token in captureTokens ?? []"
                        :key="token.id"
                        class="flex items-center justify-between gap-3 rounded-xl border border-os-line px-3 py-2 text-[12.5px]"
                    >
                        <div>
                            <p class="font-bold text-os-ink">{{ token.name }}</p>
                            <p class="text-os-sub">
                                {{ token.token_prefix }}… ·
                                {{ token.revoked_at ? '失効済' : '有効' }}
                            </p>
                        </div>
                        <Form
                            v-if="!token.revoked_at"
                            v-bind="destroy.form(token.id)"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                class="h-8 rounded-lg text-[11px]"
                            >
                                失効
                            </Button>
                        </Form>
                    </li>
                </ul>
            </template>
        </section>

        <div
            class="rounded-2xl border border-dashed border-os-line bg-os-kioku-paper p-6 text-center text-sm leading-relaxed text-os-sub"
        >
            Obsidian 出力・Clear Dawn / ヨユウへの行動化は Detail
            画面の明示操作から利用できます（feature flag 既定 OFF）。
        </div>
    </div>
</template>
