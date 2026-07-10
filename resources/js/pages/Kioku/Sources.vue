<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { SOURCE_TYPES, type SourceTypeKey } from '@/lib/kiokuMeta';

defineProps<{
    sourceCounts?: Record<string, number>;
}>();

defineOptions({
    layout: {
        title: 'キオク',
        subtitle: '取り込み元',
    },
});
</script>

<template>
    <div class="space-y-5">
        <Head title="取り込み元 — キオク" />
        <section
            class="mx-auto max-w-lg rounded-2xl border border-os-line bg-os-kioku-paper p-5 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
        >
            <div
                class="mb-3 text-[11.5px] font-bold tracking-wide text-os-faint"
            >
                取り込み元
            </div>
            <div
                v-for="(meta, key) in SOURCE_TYPES"
                :key="key"
                class="flex items-center gap-2 border-b border-os-line py-2 text-[12.5px] last:border-0"
                :class="meta.muted ? 'opacity-45' : ''"
            >
                <component :is="meta.icon" :size="13" class="text-os-sub" />
                <span class="flex-1 text-os-ink">{{ meta.label }}</span>
                <span class="font-mono text-xs text-os-faint">{{
                    sourceCounts?.[key as SourceTypeKey] ?? 0
                }}</span>
            </div>
            <p class="mt-3 text-[11px] leading-relaxed text-os-faint">
                ヨユウ・Clear Dawnからの自動保存とSlack連携は、本実装ではイベント/コネクタ経由になります。
            </p>
        </section>
    </div>
</template>
