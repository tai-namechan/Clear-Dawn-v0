<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChartNoAxesCombined, House, ListChecks, Settings2, Utensils } from '@lucide/vue';
import { computed } from 'vue';

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0] ?? '');
const items = [
    { label: 'ホーム', href: '/dashboard', icon: House, paths: ['/dashboard', '/life-areas', '/goals'] },
    { label: '記録', href: '/records', icon: ChartNoAxesCombined, paths: ['/records', '/history'] },
    { label: '食事', href: '/meals', icon: Utensils, paths: ['/meals'] },
    { label: 'ルーティン', href: '/routines', icon: ListChecks, paths: ['/routines', '/programs', '/plans', '/sessions', '/routine-items'] },
    { label: '設定', href: '/settings/profile', icon: Settings2, paths: ['/settings'] },
];

function isActive(paths: string[]): boolean {
    return paths.some((prefix) => currentPath.value === prefix || currentPath.value.startsWith(prefix + '/'));
}
</script>

<template>
    <nav v-if="!currentPath.startsWith('/sessions/')" class="cd-mobile-nav" aria-label="Clear Dawn モバイルナビゲーション">
        <Link
            v-for="item in items"
            :key="item.href"
            :href="item.href"
            :aria-current="isActive(item.paths) ? 'page' : undefined"
        >
            <component :is="item.icon" :size="20" :stroke-width="1.8" aria-hidden="true" />
            <span>{{ item.label }}</span>
        </Link>
    </nav>
</template>
