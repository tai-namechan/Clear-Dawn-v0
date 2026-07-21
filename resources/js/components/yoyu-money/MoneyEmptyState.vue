<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Props {
    title: string;
    description: string;
    actionLabel?: string;
    actionHref?: string;
}

withDefaults(defineProps<Props>(), {
    actionLabel: undefined,
    actionHref: undefined,
});
</script>

<template>
    <div
        class="rounded-xl border border-dashed border-os-line bg-os-yoyu-bg/60 px-4 py-8 text-center"
    >
        <p class="text-[15px] font-bold text-os-ink">{{ title }}</p>
        <p class="mt-2 text-[13px] text-os-sub">{{ description }}</p>
        <Link
            v-if="actionHref && actionLabel"
            :href="actionHref"
            class="mt-4 inline-flex min-h-10 items-center rounded-lg bg-os-yoyu px-4 text-[13px] font-semibold text-white hover:bg-os-yoyu/90 focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
        >
            {{ actionLabel }}
        </Link>
        <div v-else-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
