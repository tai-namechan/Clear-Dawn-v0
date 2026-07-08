<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

type HubTab = {
    label: string;
    href: string;
    matchPrefix?: boolean;
};

const tabs: HubTab[] = [
    { label: '今日の実行', href: '/today', matchPrefix: true },
    { label: 'ルーティン', href: '/routines', matchPrefix: true },
    { label: '実施項目', href: '/routine-items' },
    { label: '記録', href: '/history' },
];

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

function isActive(tab: HubTab): boolean {
    if (tab.matchPrefix) {
        return isCurrentOrParentUrl(tab.href);
    }

    return isCurrentUrl(tab.href);
}
</script>

<template>
    <nav
        aria-label="ルーティンハブ"
        class="flex flex-wrap gap-2 border-b border-cd-line/60 pb-3"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            :aria-current="isActive(tab) ? 'page' : undefined"
            class="rounded-full border px-4 py-1.5 font-sans text-sm tracking-[0.06em] transition-colors"
            :class="
                isActive(tab)
                    ? 'border-primary/30 bg-primary/10 text-primary'
                    : 'border-cd-line/80 bg-white/60 text-cd-ink-muted hover:border-cd-line hover:text-cd-ink'
            "
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
