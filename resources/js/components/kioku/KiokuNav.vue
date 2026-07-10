<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home, settings, sources } from '@/routes/kioku';

defineProps<{
    active: 'home' | 'sources' | 'settings';
}>();
</script>

<template>
    <nav class="flex flex-wrap gap-1.5 text-sm">
        <Link
            :href="home()"
            class="rounded-full px-3.5 py-1.5 font-medium transition-colors"
            :class="
                active === 'home'
                    ? 'bg-os-kioku-soft text-os-kioku'
                    : 'text-os-sub hover:bg-white/80'
            "
        >
            記憶
        </Link>
        <Link
            :href="sources()"
            class="rounded-full px-3.5 py-1.5 font-medium transition-colors"
            :class="
                active === 'sources'
                    ? 'bg-os-kioku-soft text-os-kioku'
                    : 'text-os-sub hover:bg-white/80'
            "
        >
            取り込み元
        </Link>
        <Link
            :href="settings()"
            class="rounded-full px-3.5 py-1.5 font-medium transition-colors"
            :class="
                active === 'settings'
                    ? 'bg-os-kioku-soft text-os-kioku'
                    : 'text-os-sub hover:bg-white/80'
            "
        >
            設定
        </Link>
    </nav>
</template>
