<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { clearDawnNavIcons } from '@/lib/clearDawnNavIcons';
import {
    clearDawnNavItems as items,
    isNavPathActive,
} from '@/lib/clearDawnNavigation.mjs';

const page = usePage();
</script>

<template>
    <nav class="cd-mobile-nav" aria-label="Clear Dawn モバイルナビゲーション">
        <Link
            v-for="item in items"
            :key="item.key"
            :href="item.href"
            :aria-current="
                isNavPathActive(item.paths, page.url) ? 'page' : undefined
            "
        >
            <component
                :is="clearDawnNavIcons[item.key]"
                :size="20"
                :stroke-width="1.8"
                aria-hidden="true"
            />
            <span>{{ item.shortLabel }}</span>
        </Link>
    </nav>
</template>
