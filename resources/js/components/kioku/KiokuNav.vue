<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home, settings, sources } from '@/routes/kioku';
import { index as lettersIndex } from '@/routes/kioku/letters';
import { index as memoriesIndex } from '@/routes/kioku/memories';

defineProps<{
    active: 'home' | 'library' | 'letters' | 'sources' | 'settings';
}>();
</script>

<template>
    <nav
        class="flex flex-wrap gap-1.5 text-sm"
        aria-label="キオクメニュー"
    >
        <Link
            :href="home()"
            class="rounded-full px-3.5 py-1.5 font-medium transition-colors"
            :class="
                active === 'home'
                    ? 'bg-os-kioku text-white'
                    : 'border border-os-line bg-os-kioku-paper text-os-sub hover:bg-white'
            "
        >
            ホーム
        </Link>
        <Link
            :href="memoriesIndex()"
            class="rounded-full px-3.5 py-1.5 font-medium transition-colors"
            :class="
                active === 'library'
                    ? 'bg-os-kioku text-white'
                    : 'border border-os-line bg-os-kioku-paper text-os-sub hover:bg-white'
            "
        >
            キオクを探す
        </Link>
        <Link
            :href="lettersIndex()"
            class="rounded-full px-3.5 py-1.5 font-medium transition-colors"
            :class="
                active === 'letters'
                    ? 'bg-os-kioku text-white'
                    : 'border border-os-line bg-os-kioku-paper text-os-sub hover:bg-white'
            "
        >
            キオク便り
        </Link>
        <Link
            :href="sources()"
            class="rounded-full px-3.5 py-1.5 font-medium transition-colors"
            :class="
                active === 'sources'
                    ? 'bg-os-kioku text-white'
                    : 'border border-os-line bg-os-kioku-paper text-os-sub hover:bg-white'
            "
        >
            取り込み元
        </Link>
        <Link
            :href="settings()"
            class="rounded-full px-3.5 py-1.5 font-medium transition-colors"
            :class="
                active === 'settings'
                    ? 'bg-os-kioku text-white'
                    : 'border border-os-line bg-os-kioku-paper text-os-sub hover:bg-white'
            "
        >
            設定
        </Link>
    </nav>
</template>
