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
        class="group block rounded-2xl border border-os-line/90 bg-white px-4 py-3.5 transition-colors hover:border-os-kioku/25 hover:bg-white"
        :class="
            memory.status === 'enriching' || memory.status === 'captured'
                ? 'cursor-default'
                : 'cursor-pointer'
        "
    >
        <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                <span
                    v-if="
                        memory.status === 'enriching' ||
                        memory.status === 'captured'
                    "
                    class="inline-flex items-center gap-1.5 rounded-full bg-os-kioku-soft px-2.5 py-1 text-[11px] font-medium text-os-kioku"
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
                    class="rounded-full bg-[#F6EEEC] px-2.5 py-1 text-[11px] font-medium text-[#B86B66]"
                >
                    整理失敗（原文は保存済み）
                </span>
                <h3 class="truncate text-[14px] font-semibold text-os-ink">
                    {{ memory.title }}
                </h3>
            </div>
            <span class="shrink-0 text-[11px] text-os-faint">
                {{ formatAgo(memory.captured_at) }}
            </span>
        </div>

        <p class="line-clamp-2 text-[12.5px] leading-relaxed text-os-sub">
            <template
                v-if="
                    memory.status === 'enriching' || memory.status === 'captured'
                "
            >
                {{ memory.raw_content.slice(0, 100) }}
            </template>
            <template v-else>
                {{ memory.summary || memory.raw_content.slice(0, 100) }}
            </template>
        </p>

        <div
            v-if="memory.status === 'ready' && memory.tags?.length"
            class="mt-2 flex flex-wrap items-center gap-1.5"
        >
            <SourceBadge :source="memory.source_type" />
            <span
                v-for="tag in memory.tags"
                :key="tag"
                class="rounded-full bg-os-kioku-soft/70 px-2 py-0.5 text-[10.5px] text-os-kioku"
            >
                #{{ tag }}
            </span>
            <span
                class="ml-auto text-[10px] tracking-wide"
                :style="{ color: memoryTypeMeta(memory.memory_type).color }"
                aria-label="重要度"
            >
                {{ '★'.repeat(memory.importance)
                }}{{ '☆'.repeat(5 - memory.importance) }}
            </span>
        </div>
    </Link>
</template>
