<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Loader2 } from '@lucide/vue';
import SourceBadge from '@/components/kioku/SourceBadge.vue';
import TypeChip from '@/components/kioku/TypeChip.vue';
import { formatAgo, memoryTypeMeta } from '@/lib/kiokuMeta';
import { show } from '@/routes/kioku/memories';
import type { KiokuMemory } from '@/types/kioku';

defineProps<{
    memory: KiokuMemory;
}>();
</script>

<template>
    <Link
        :href="show(memory.id)"
        class="group relative block rounded-2xl border border-os-line bg-os-kioku-paper p-4 shadow-[0_1px_3px_rgba(43,41,36,0.05)] transition-[border-color] hover:border-os-kioku/40"
        :class="
            memory.status === 'enriching' || memory.status === 'captured'
                ? 'cursor-default'
                : 'cursor-pointer'
        "
    >
        <div
            class="absolute top-4 bottom-4 left-0 w-[3px] rounded-full opacity-70"
            :style="{
                background: memoryTypeMeta(memory.memory_type).color,
            }"
        />

        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-if="
                        memory.status === 'enriching' ||
                        memory.status === 'captured'
                    "
                    class="inline-flex items-center gap-1.5 rounded-full bg-os-kioku-soft px-2.5 py-1 text-[11.5px] font-bold text-os-kioku"
                >
                    <Loader2 :size="11" class="animate-spin" />
                    AIが整理中…
                </span>
                <TypeChip
                    v-else-if="memory.memory_type"
                    :type="memory.memory_type"
                    small
                />
                <span
                    v-else-if="memory.status === 'failed'"
                    class="rounded-full bg-[#F8E9E4] px-2.5 py-1 text-[11.5px] font-bold text-[#C05A48]"
                >
                    整理失敗（原文は保存済み）
                </span>
                <SourceBadge :source="memory.source_type" />
            </div>
            <span class="text-[11px] text-os-faint">{{
                formatAgo(memory.captured_at)
            }}</span>
        </div>

        <div class="text-[14.5px] font-bold text-os-ink">
            {{ memory.title }}
        </div>
        <p class="mt-1 text-[13px] leading-relaxed text-os-sub">
            <template
                v-if="
                    memory.status === 'enriching' || memory.status === 'captured'
                "
            >
                {{ memory.raw_content.slice(0, 80) }}
            </template>
            <template v-else>
                {{ memory.summary || memory.raw_content.slice(0, 80) }}
            </template>
        </p>

        <div
            v-if="memory.status === 'ready'"
            class="mt-2.5 flex flex-wrap items-center gap-2.5"
        >
            <span class="text-[11px] tracking-wide text-[#B8862B]">
                <span>{{ '★'.repeat(memory.importance) }}</span>
                <span class="text-os-line">{{
                    '★'.repeat(5 - memory.importance)
                }}</span>
            </span>
            <span
                v-for="tag in memory.tags"
                :key="tag"
                class="text-[11px] text-os-kioku"
            >
                #{{ tag }}
            </span>
        </div>
    </Link>
</template>
