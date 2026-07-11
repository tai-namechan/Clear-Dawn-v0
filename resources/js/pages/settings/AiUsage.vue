<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Bot } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/ai-usage';

interface ModelSpend {
    model: string;
    spent_usd: string;
}

interface FeatureSpend {
    feature: string;
    count: number;
    spent_usd: string;
}

interface UsageSummary {
    period: string;
    spent_usd: string;
    reserved_usd: string;
    limit_usd: string;
    remaining_usd: string;
    progress_ratio: string;
    warning: boolean;
    at_limit: boolean;
    expired_count: number;
    by_model: ModelSpend[];
    by_feature: FeatureSpend[];
}

interface Props {
    usage: UsageSummary;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'AI利用量',
                href: edit(),
            },
        ],
    },
});

const progressPercent = computed(() => {
    const ratio = Number(props.usage.progress_ratio);
    if (!Number.isFinite(ratio)) {
        return 0;
    }

    return Math.min(100, Math.max(0, Math.round(ratio * 100)));
});

const featureLabels: Record<string, string> = {
    'kioku.classify': 'キオク整理（分類）',
    'kioku.extract': 'キオク整理（抽出）',
    'yoyu.briefing': '朝ブリーフィング',
    'yoyu.chat': 'ヨユウチャット',
};

function labelFeature(feature: string): string {
    return featureLabels[feature] ?? feature;
}

function formatUsd(value: string): string {
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return `$${value}`;
    }

    return `$${n.toFixed(4)}`;
}
</script>

<template>
    <Head title="AI利用量" />

    <h1 class="sr-only">AI利用量</h1>

    <div class="space-y-8">
        <div
            v-if="usage.at_limit"
            class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 font-sans text-sm text-cd-ink"
            role="alert"
        >
            今月のAI利用上限に達しました。原文の保存やタスク操作など、AI以外の機能は引き続き使えます。AI処理は翌月までお待ちください。
        </div>
        <div
            v-else-if="usage.warning"
            class="rounded-xl border border-cd-lavender-mist/50 bg-cd-lavender-mist/20 px-4 py-3 font-sans text-sm text-cd-dawn-deep"
            role="status"
        >
            今月のAI利用量が上限の80%以上に達しています。残り
            {{ formatUsd(usage.remaining_usd) }} です。
        </div>

        <div class="cd-panel p-6 md:p-8">
            <Heading
                variant="small"
                title="AI利用量"
                description="今月の確定利用額と処理中の予約枠を確認できます。"
                class="mb-6"
            />

            <div class="space-y-6 font-sans">
                <div class="flex items-start gap-3">
                    <Bot
                        :size="20"
                        :stroke-width="1.6"
                        class="mt-0.5 shrink-0 text-cd-ink-muted"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1 space-y-2">
                        <p class="text-sm text-cd-ink-muted">
                            対象月 {{ usage.period }}
                        </p>
                        <p class="text-2xl font-semibold tracking-tight text-cd-ink">
                            {{ formatUsd(usage.spent_usd) }}
                            <span class="text-base font-normal text-cd-ink-muted">
                                / {{ formatUsd(usage.limit_usd) }}
                            </span>
                        </p>
                        <div
                            class="h-2 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-valuenow="progressPercent"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-full rounded-full bg-cd-dawn-deep transition-[width]"
                                :style="{ width: `${progressPercent}%` }"
                            />
                        </div>
                        <dl
                            class="grid gap-2 text-sm text-cd-ink sm:grid-cols-3"
                        >
                            <div>
                                <dt class="text-cd-ink-muted">処理中の予約</dt>
                                <dd class="font-medium">
                                    {{ formatUsd(usage.reserved_usd) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-cd-ink-muted">利用可能残額</dt>
                                <dd class="font-medium">
                                    {{ formatUsd(usage.remaining_usd) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-cd-ink-muted">進捗率</dt>
                                <dd class="font-medium">
                                    {{ progressPercent }}%
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <p
                    v-if="usage.expired_count > 0"
                    class="rounded-lg bg-muted/60 px-3 py-2 text-sm text-cd-ink-muted"
                >
                    結果確認不能の処理が {{ usage.expired_count }}
                    件あり、予約額を保守的に確定利用へ移しています。
                </p>
            </div>
        </div>

        <div class="cd-panel p-6 md:p-8">
            <Heading
                variant="small"
                title="モデル別"
                description="今月の確定利用額の内訳です。"
                class="mb-4"
            />
            <ul
                v-if="usage.by_model.length > 0"
                class="space-y-2 font-sans text-sm"
            >
                <li
                    v-for="row in usage.by_model"
                    :key="row.model"
                    class="flex items-center justify-between gap-3 border-b border-border/60 py-2 last:border-b-0"
                >
                    <span class="truncate text-cd-ink">{{ row.model }}</span>
                    <span class="shrink-0 font-medium text-cd-ink">{{
                        formatUsd(row.spent_usd)
                    }}</span>
                </li>
            </ul>
            <p v-else class="font-sans text-sm text-cd-ink-muted">
                まだモデル別の利用はありません。
            </p>
        </div>

        <div class="cd-panel p-6 md:p-8">
            <Heading
                variant="small"
                title="機能別"
                description="呼び出し回数と確定利用額です。"
                class="mb-4"
            />
            <ul
                v-if="usage.by_feature.length > 0"
                class="space-y-2 font-sans text-sm"
            >
                <li
                    v-for="row in usage.by_feature"
                    :key="row.feature"
                    class="flex items-center justify-between gap-3 border-b border-border/60 py-2 last:border-b-0"
                >
                    <span class="min-w-0">
                        <span class="block truncate text-cd-ink">{{
                            labelFeature(row.feature)
                        }}</span>
                        <span class="text-xs text-cd-ink-muted"
                            >{{ row.count }} 回</span
                        >
                    </span>
                    <span class="shrink-0 font-medium text-cd-ink">{{
                        formatUsd(row.spent_usd)
                    }}</span>
                </li>
            </ul>
            <p v-else class="font-sans text-sm text-cd-ink-muted">
                まだ機能別の利用はありません。
            </p>
        </div>
    </div>
</template>
