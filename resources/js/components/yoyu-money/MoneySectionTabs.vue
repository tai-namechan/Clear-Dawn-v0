<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { MoneySectionTab } from '@/lib/yoyuMoney/navigation';

interface Props {
    tabs: MoneySectionTab[];
    active: string;
    label?: string;
}

withDefaults(defineProps<Props>(), {
    label: 'セクション',
});
</script>

<template>
    <nav
        :aria-label="label"
        class="flex gap-1 overflow-x-auto border-b border-os-line pb-px"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            class="shrink-0 border-b-2 px-3 py-2 text-[13px] font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
            :class="
                active === tab.key
                    ? 'border-os-yoyu text-os-yoyu'
                    : 'border-transparent text-os-sub hover:text-os-ink'
            "
            :aria-current="active === tab.key ? 'page' : undefined"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
