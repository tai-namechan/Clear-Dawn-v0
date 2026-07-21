<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import MoneyStatusBadge from '@/components/yoyu-money/MoneyStatusBadge.vue';

type SetupStep = {
    key: string;
    label: string;
    description: string;
    status: 'complete' | 'incomplete' | 'optional';
    href: string;
    required: boolean;
};

interface Props {
    steps: SetupStep[];
    nextStepKey: string | null;
    completedRequiredCount: number;
    requiredCount: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    dismiss: [];
}>();

function nextHref(): string {
    const next = props.steps.find((step) => step.key === props.nextStepKey);

    return next?.href ?? '/yoyu/money/accounts?compose=1';
}
</script>

<template>
    <section
        class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        aria-labelledby="money-setup-heading"
    >
        <h2 id="money-setup-heading" class="text-base font-bold text-os-ink">
            お金の余裕を計算する準備をしましょう
        </h2>
        <p class="mt-1 text-[13px] text-os-sub">
            必要な設定が揃うと、「安全に使える金額」を表示できます（{{
                completedRequiredCount
            }}/{{ requiredCount }}）
        </p>

        <ol class="mt-4 max-w-xl space-y-2.5">
            <li
                v-for="(step, index) in steps"
                :key="step.key"
                class="rounded-xl bg-os-yoyu-bg/70 px-3 py-2.5"
            >
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <p class="min-w-0 text-[13px] font-semibold text-os-ink">
                        {{ index + 1 }}. {{ step.label }}
                    </p>
                    <div class="flex items-center gap-2">
                        <MoneyStatusBadge
                            v-if="step.status === 'complete'"
                            label="完了"
                            tone="positive"
                        />
                        <MoneyStatusBadge
                            v-else-if="step.status === 'optional'"
                            label="任意"
                            tone="info"
                        />
                        <MoneyStatusBadge
                            v-else
                            label="未完了"
                            tone="caution"
                        />
                        <Link
                            v-if="step.status !== 'complete'"
                            :href="step.href"
                            class="text-[12px] font-semibold text-os-yoyu hover:underline focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                        >
                            設定
                        </Link>
                    </div>
                </div>
                <p class="mt-1 text-[12px] text-os-sub">
                    {{ step.description }}
                </p>
            </li>
        </ol>

        <div class="mt-4 flex flex-wrap gap-2">
            <Link
                :href="nextHref()"
                class="inline-flex min-h-10 items-center rounded-lg bg-os-yoyu px-4 text-[13px] font-semibold text-white hover:bg-os-yoyu/90 focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
            >
                次の設定へ進む
            </Link>
            <button
                type="button"
                class="inline-flex min-h-10 items-center rounded-lg border border-os-line px-4 text-[13px] font-semibold text-os-sub hover:bg-os-yoyu-soft focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                @click="emit('dismiss')"
            >
                あとで設定する
            </button>
        </div>
    </section>
</template>
