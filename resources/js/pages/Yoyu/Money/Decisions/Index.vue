<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import MoneySubnav from '@/components/yoyu-money/MoneySubnav.vue';
import type { MoneyDecisionRow } from '@/lib/yoyuMoney/types';

interface Props {
    decisions: MoneyDecisionRow[];
}

defineProps<Props>();

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
            },
        },
    );
}

function payloadPreview(payload: Record<string, unknown> | null | undefined): string {
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

function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        planned: '予定',
        action_required: '要対応',
        executed: '実行済',
        reviewed: '振り返り済',
        canceled: '取消',
    };

    return labels[status] ?? status;
}

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: '判断履歴',
    },
});
</script>

<template>
    <div class="mx-auto max-w-[720px] space-y-4">
        <Head title="判断履歴 — お金の余裕" />

        <MoneySubnav active="decisions" />

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">判断を記録</h2>
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="submitCreate"
            >
                <label class="text-[12px] text-os-sub">
                    タイトル
                    <input
                        v-model="createForm.title"
                        type="text"
                        required
                        class="mt-1 block w-52 rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    決定日
                    <input
                        v-model="createForm.decided_on"
                        type="date"
                        class="mt-1 block rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    メモ
                    <input
                        v-model="createForm.memo"
                        type="text"
                        class="mt-1 block w-48 rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <Button type="submit" size="sm" class="rounded-full">
                    記録
                </Button>
            </form>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">タイムライン</h2>
            <p v-if="decisions.length === 0" class="text-[13px] text-os-sub">
                判断履歴はまだありません。
            </p>
            <ol v-else class="relative space-y-4 border-l border-os-line pl-4">
                <li
                    v-for="decision in decisions"
                    :key="decision.id"
                    class="relative"
                >
                    <span
                        class="absolute top-1.5 -left-[21px] h-2.5 w-2.5 rounded-full bg-os-yoyu"
                    />
                    <div
                        class="rounded-xl border border-os-line bg-white px-4 py-3"
                    >
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-2"
                        >
                            <p class="font-bold text-os-ink">
                                {{ decision.title }}
                            </p>
                            <p class="text-[12px] text-os-sub">
                                {{ decision.decided_on }} ·
                                {{ statusLabel(decision.status) }}
                            </p>
                        </div>
                        <p
                            v-if="decision.memo"
                            class="mt-1 text-[13px] text-os-sub"
                        >
                            {{ decision.memo }}
                        </p>
                        <dl class="mt-3 space-y-2 text-[12px]">
                            <div>
                                <dt class="font-semibold text-os-sub">Before</dt>
                                <dd
                                    class="mt-0.5 break-all font-mono text-[11px] text-os-ink"
                                >
                                    {{
                                        payloadPreview(decision.before_payload)
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-os-sub">
                                    Expected
                                </dt>
                                <dd
                                    class="mt-0.5 break-all font-mono text-[11px] text-os-ink"
                                >
                                    {{
                                        payloadPreview(
                                            decision.expected_effect_payload,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-os-sub">Actual</dt>
                                <dd
                                    class="mt-0.5 break-all font-mono text-[11px] text-os-ink"
                                >
                                    {{
                                        payloadPreview(
                                            decision.actual_effect_payload,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <p
                            v-if="decision.reviewed_at"
                            class="mt-2 text-[11px] text-os-sub"
                        >
                            振り返り: {{ decision.reviewed_at }}
                        </p>
                    </div>
                </li>
            </ol>
        </section>
    </div>
</template>
