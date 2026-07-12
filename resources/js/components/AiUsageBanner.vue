<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { edit as editAiUsage } from '@/routes/ai-usage';

type AiUsageBanner = {
    warning: boolean;
    at_limit: boolean;
    progress_ratio: string;
    remaining_usd: string;
    limit_usd: string;
    spent_usd: string;
    reserved_usd: string;
};

const page = usePage();

const banner = computed(
    () => (page.props.aiUsageBanner as AiUsageBanner | null | undefined) ?? null,
);

const message = computed(() => {
    if (!banner.value) {
        return '';
    }

    if (banner.value.at_limit) {
        return '今月のAI利用上限に達しました。原文の保存やタスク操作など、AI以外の機能は引き続き使えます。';
    }

    return '今月のAI利用量が上限の80%以上に達しています。利用状況を確認してください。';
});
</script>

<template>
    <div
        v-if="banner"
        class="border-b px-4 py-3 font-sans text-sm md:px-6"
        :class="
            banner.at_limit
                ? 'border-destructive/30 bg-destructive/10 text-cd-ink'
                : 'border-cd-lavender-mist/50 bg-cd-lavender-mist/20 text-cd-dawn-deep'
        "
        :role="banner.at_limit ? 'alert' : 'status'"
        data-test="ai-usage-banner"
    >
        <div
            class="mx-auto flex max-w-[1060px] flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="min-w-0">{{ message }}</p>
            <Link
                :href="editAiUsage()"
                class="shrink-0 font-semibold underline-offset-2 hover:underline"
                data-test="ai-usage-banner-link"
            >
                AI利用量を見る
            </Link>
        </div>
    </div>
</template>
