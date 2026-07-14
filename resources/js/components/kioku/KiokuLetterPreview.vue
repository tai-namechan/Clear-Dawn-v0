<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Mail } from '@lucide/vue';
import { computed } from 'vue';
import {
    kiokuLetterPreviewLabel,
    kiokuLetterPreviewMode,
    kiokuLetterWeekLabel,
} from '@/lib/kiokuLetter.mjs';
import { show } from '@/routes/kioku/letters';
import type { KiokuLetterSummary } from '@/types/kiokuLetter';

const props = defineProps<{
    letters: KiokuLetterSummary[];
}>();

const latest = computed(() => props.letters[0] ?? null);
const past = computed(() => props.letters.slice(1));
const latestMode = computed(() =>
    latest.value ? kiokuLetterPreviewMode(latest.value) : null,
);
</script>

<template>
    <!-- Weekly concierge letters (up to 4). Rendered only when at least one
         letter exists; the capture flow above/left is never touched. -->
    <section
        v-if="latest"
        class="rounded-2xl border border-os-line bg-os-kioku-paper p-4 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
    >
        <div
            class="mb-2.5 flex items-center gap-1.5 text-[11.5px] font-bold tracking-wide text-os-kioku"
        >
            <Mail :size="13" />
            今週のキオク便り
            <span class="ml-auto font-normal text-os-sub">{{
                kiokuLetterWeekLabel(latest.week_start)
            }}</span>
        </div>

        <p
            v-if="latestMode === 'empty'"
            class="text-[12.5px] leading-relaxed text-os-sub"
        >
            {{ kiokuLetterPreviewLabel(latest) }}
        </p>
        <Link
            v-else-if="latestMode === 'unread'"
            :href="show.url(latest.id)"
            class="flex h-11 items-center justify-center gap-2 rounded-xl bg-os-kioku text-[13.5px] font-bold text-white shadow-[0_3px_10px_rgba(62,86,136,0.28)] transition-colors hover:bg-os-kioku/90 motion-reduce:transition-none"
        >
            便りを開く
        </Link>
        <Link
            v-else
            :href="show.url(latest.id)"
            class="flex items-center justify-between gap-2 rounded-xl bg-os-kioku-soft px-3.5 py-2.5 text-[12.5px] font-bold text-os-kioku transition-colors hover:bg-os-kioku/10 motion-reduce:transition-none"
        >
            {{ kiokuLetterPreviewLabel(latest) }}
            <ChevronRight :size="14" />
        </Link>

        <div v-if="past.length" class="mt-2.5 space-y-0.5">
            <Link
                v-for="letter in past"
                :key="letter.id"
                :href="show.url(letter.id)"
                class="flex items-center justify-between gap-2 border-b border-os-line py-1.5 text-[11.5px] text-os-sub last:border-0 hover:text-os-ink"
            >
                <span>{{ kiokuLetterWeekLabel(letter.week_start) }}</span>
                <span>{{ kiokuLetterPreviewLabel(letter) }}</span>
            </Link>
        </div>
    </section>
</template>
