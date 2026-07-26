<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    teamSlug: string;
    athleteId: number;
    active:
        'overview' | 'training' | 'meals' | 'condition' | 'goals' | 'report';
}

const props = defineProps<Props>();

const tabs = computed(() => {
    const base = `/t/${props.teamSlug}/athletes/${props.athleteId}`;

    return [
        { key: 'overview', label: '概要', href: base },
        { key: 'training', label: 'トレーニング', href: `${base}/training` },
        { key: 'meals', label: '食事', href: `${base}/meals` },
        {
            key: 'condition',
            label: 'コンディション',
            href: `${base}/condition`,
        },
        { key: 'goals', label: '目標', href: `${base}/goals` },
        { key: 'report', label: 'レポート', href: `${base}/report` },
    ] as const;
});
</script>

<template>
    <div class="mt-6 overflow-x-auto border-b border-slate-200">
        <nav class="flex min-w-max gap-6" aria-label="選手詳細タブ">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.href"
                class="border-b-2 px-1 py-3 text-sm font-medium whitespace-nowrap"
                :class="
                    tab.key === active
                        ? 'border-violet-600 text-violet-700'
                        : 'border-transparent text-slate-500 hover:text-slate-700'
                "
            >
                {{ tab.label }}
            </Link>
        </nav>
    </div>
</template>
