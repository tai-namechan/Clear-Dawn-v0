<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Loader2 } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import SourceBadge from '@/components/kioku/SourceBadge.vue';
import TypeChip from '@/components/kioku/TypeChip.vue';
import { kiokuEnrichmentLabel } from '@/composables/useKiokuEnrichmentPoll';
import {
    isKiokuMemoryCardEnriching,
    isKiokuMemoryCardNavigable,
    kiokuMemoryDisplayTitle,
} from '@/lib/kiokuMemoryCard.mjs';
import { formatAgo, memoryTypeMeta, sourceTypeMeta } from '@/lib/kiokuMeta';
import { show } from '@/routes/kioku/memories';
import type { KiokuMemory } from '@/types/kioku';

const props = defineProps<{
    memory: KiokuMemory;
    transcriptionEnabled?: boolean;
}>();

const nowMs = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;

/** Enrichment chrome only — does not gate navigation (voice stays openable). */
const enriching = computed(() =>
    isKiokuMemoryCardEnriching(props.memory, {
        transcriptionEnabled: props.transcriptionEnabled ?? true,
    }),
);

/** Voice keeps a durable audio original, so Detail stays reachable. */
const navigable = computed(() => isKiokuMemoryCardNavigable(props.memory));

const displayTitle = computed(() => kiokuMemoryDisplayTitle(props.memory));

const titleClass = computed(
    () => sourceTypeMeta(props.memory.source_type).titleClass ?? 'text-os-ink',
);

/** Voice memories have no raw_content; fall back to transcript, then a label. */
const excerpt = computed(() => {
    const text = props.memory.raw_content ?? props.memory.transcript_text;

    if (text !== null && text !== '') {
        return text.slice(0, 80);
    }

    return props.memory.source_type === 'voice' ? '音声メモ' : '';
});

const enrichmentLabel = computed(() =>
    kiokuEnrichmentLabel(props.memory, nowMs.value),
);

function clearTimer(): void {
    if (timer) {
        clearInterval(timer);
        timer = undefined;
    }
}

function ensureTimer(): void {
    clearTimer();

    if (enriching.value) {
        timer = setInterval(() => {
            nowMs.value = Date.now();
        }, 1000);
    }
}

onMounted(() => {
    ensureTimer();
});

onUnmounted(() => {
    clearTimer();
});

watch(enriching, () => {
    ensureTimer();
});
</script>

<template>
    <component
        :is="navigable ? Link : 'div'"
        :href="navigable ? show(memory.id) : undefined"
        class="group relative block rounded-2xl border border-os-line bg-os-kioku-paper p-4 shadow-[0_1px_3px_rgba(43,41,36,0.05)] transition-[border-color]"
        :class="
            navigable
                ? 'cursor-pointer hover:border-os-kioku/40'
                : 'cursor-default'
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
                    v-if="enriching"
                    class="inline-flex items-center gap-1.5 rounded-full bg-os-kioku-soft px-2.5 py-1 text-[11.5px] font-bold text-os-kioku"
                >
                    <Loader2 :size="11" class="animate-spin" />
                    {{ enrichmentLabel }}
                </span>
                <span
                    v-else-if="memory.status === 'failed'"
                    class="rounded-full bg-[#F8E9E4] px-2.5 py-1 text-[11.5px] font-bold text-[#C05A48]"
                >
                    AI整理に失敗しました
                </span>
                <TypeChip
                    v-else-if="memory.memory_type"
                    :type="memory.memory_type"
                    small
                />
                <SourceBadge :source="memory.source_type" />
            </div>
            <span class="text-[11px] text-os-sub">{{
                formatAgo(memory.captured_at)
            }}</span>
        </div>

        <div class="text-[14.5px] font-bold" :class="titleClass">
            {{ displayTitle }}
        </div>
        <p class="mt-1 text-[13px] leading-relaxed text-os-sub">
            <template v-if="enriching || memory.status === 'failed'">
                {{ excerpt }}
            </template>
            <template v-else>
                {{ memory.summary || excerpt }}
            </template>
        </p>

        <div
            v-if="memory.status === 'ready'"
            class="mt-2.5 flex flex-wrap items-center justify-between gap-2.5"
        >
            <div class="flex flex-wrap items-center gap-2.5">
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
        </div>
    </component>
</template>
