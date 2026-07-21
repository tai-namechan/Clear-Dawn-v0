<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import MoneyEmptyState from '@/components/yoyu-money/MoneyEmptyState.vue';
import MoneyPageShell from '@/components/yoyu-money/MoneyPageShell.vue';
import { decisionStatusLabel } from '@/lib/yoyuMoney/labels';
import { moneyPlanTabs } from '@/lib/yoyuMoney/navigation';
import type { MoneyDecisionRow } from '@/lib/yoyuMoney/types';

interface Props {
    decisions: MoneyDecisionRow[];
}

defineProps<Props>();

const page = usePage();
const drawerOpen = ref(false);

const createForm = reactive({
    title: '',
    decided_on: '',
    memo: '',
});

function submitCreate(): void {
    router.post(
        '/yoyu/money/decisions',
        {
            title: createForm.title,
            decided_on: createForm.decided_on || null,
            memo: createForm.memo || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                createForm.title = '';
                createForm.decided_on = '';
                createForm.memo = '';
                drawerOpen.value = false;
            },
        },
    );
}

function payloadPreview(
    payload: Record<string, unknown> | null | undefined,
): string {
    if (payload == null) {
        return '—';
    }

    try {
        const text = JSON.stringify(payload);

        return text.length > 160 ? `${text.slice(0, 160)}…` : text;
    } catch {
        return '—';
    }
}

onMounted(() => {
    if (page.url.includes('compose=1')) {
        drawerOpen.value = true;
    }
});

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: '見直したこと',
    },
});
</script>

<template>
    <MoneyPageShell
        title="見直したこと"
        :section-tabs="moneyPlanTabs"
        section-active="decisions"
        section-label="計画"
        primary-active="plan"
        :show-record-menu="false"
    >
        <template #actions>
            <Button type="button" class="rounded-lg" @click="drawerOpen = true">
                ＋記録する
            </Button>
        </template>

        <MoneyEmptyState
            v-if="decisions.length === 0"
            title="見直し記録がまだありません"
            description="支払い方法の変更や節約の取り組みを記録しておくと、後から振り返りやすくなります。"
            action-label="記録する"
            action-href="/yoyu/money/decisions?compose=1"
        />

        <ol
            v-else
            class="relative space-y-4 border-l border-os-line pl-5"
        >
            <li
                v-for="decision in decisions"
                :key="decision.id"
                class="relative"
            >
                <span
                    class="absolute top-1.5 -left-[22px] h-2.5 w-2.5 rounded-full bg-os-yoyu"
                />
                <div class="rounded-2xl border border-os-line bg-white px-5 py-4 shadow-[0_1px_3px_rgba(38,48,58,0.05)]">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-bold text-os-ink">{{ decision.title }}</p>
                        <p class="text-[12px] text-os-sub">
                            {{ decision.decided_on }} ·
                            {{ decisionStatusLabel(decision.status) }}
                        </p>
                    </div>
                    <p v-if="decision.memo" class="mt-1 text-[13px] text-os-sub">
                        {{ decision.memo }}
                    </p>
                    <dl class="mt-3 space-y-2 text-[12px]">
                        <div v-if="decision.before_payload">
                            <dt class="font-semibold text-os-sub">変更前</dt>
                            <dd class="mt-0.5 break-all font-mono text-[11px] text-os-ink">
                                {{ payloadPreview(decision.before_payload) }}
                            </dd>
                        </div>
                        <div v-if="decision.expected_effect_payload">
                            <dt class="font-semibold text-os-sub">想定効果</dt>
                            <dd class="mt-0.5 break-all font-mono text-[11px] text-os-ink">
                                {{ payloadPreview(decision.expected_effect_payload) }}
                            </dd>
                        </div>
                        <div v-if="decision.actual_effect_payload">
                            <dt class="font-semibold text-os-sub">実際の効果</dt>
                            <dd class="mt-0.5 break-all font-mono text-[11px] text-os-ink">
                                {{ payloadPreview(decision.actual_effect_payload) }}
                            </dd>
                        </div>
                    </dl>
                    <p v-if="decision.reviewed_at" class="mt-2 text-[11px] text-os-faint">
                        振り返り: {{ decision.reviewed_at }}
                    </p>
                </div>
            </li>
        </ol>

        <Sheet :open="drawerOpen" @update:open="drawerOpen = $event">
            <SheetContent side="right" class="w-full border-os-line bg-white sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>見直しを記録</SheetTitle>
                    <SheetDescription>
                        支払い方法の変更や節約の取り組みを残しておくと、後から効果を確認できます。
                    </SheetDescription>
                </SheetHeader>
                <form class="mt-4 space-y-3 px-1" @submit.prevent="submitCreate">
                    <label class="block text-[12px] text-os-sub">
                        タイトル
                        <input
                            v-model="createForm.title"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/40"
                            placeholder="例: サブスクを解約した"
                        />
                    </label>
                    <label class="block text-[12px] text-os-sub">
                        決定日（任意）
                        <input
                            v-model="createForm.decided_on"
                            type="date"
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/40"
                        />
                    </label>
                    <label class="block text-[12px] text-os-sub">
                        メモ（任意）
                        <input
                            v-model="createForm.memo"
                            type="text"
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/40"
                        />
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="drawerOpen = false">
                            キャンセル
                        </Button>
                        <Button type="submit">記録する</Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    </MoneyPageShell>
</template>
