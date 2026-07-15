<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ChevronRight, Mail, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    kiokuLetterPreviewLabel,
    kiokuLetterPreviewMode,
    kiokuLetterTitleLabel,
    kiokuLetterWeekLabel,
} from '@/lib/kiokuLetter.mjs';
import { destroy, show } from '@/routes/kioku/letters';
import type { KiokuLetterSummary } from '@/types/kiokuLetter';

const props = defineProps<{
    letters: KiokuLetterSummary[];
    testLetters?: KiokuLetterSummary[];
}>();

const latest = computed(() => props.letters[0] ?? null);
const past = computed(() => props.letters.slice(1));
const latestMode = computed(() =>
    latest.value ? kiokuLetterPreviewMode(latest.value) : null,
);
const tests = computed(() => props.testLetters ?? []);

function discardTest(id: string): void {
    router.delete(destroy.url(id), { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-3">
        <!-- Live letters only in the main frame. -->
        <section
            v-if="latest"
            class="rounded-2xl border border-os-line bg-os-kioku-paper p-4 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
        >
            <div
                class="mb-2.5 flex items-center gap-1.5 text-[11.5px] font-bold tracking-wide text-os-kioku"
            >
                <Mail :size="13" />
                キオク便り
                <span class="ml-auto font-normal text-os-sub">{{
                    latest.cadence === 'daily' && latest.delivery_date
                        ? kiokuLetterTitleLabel(latest)
                        : kiokuLetterWeekLabel(latest.week_start)
                }}</span>
            </div>

            <!-- Empty still links to detail so opened_at can be recorded. -->
            <Link
                v-if="latestMode === 'empty'"
                :href="show.url(latest.id)"
                class="block text-[12.5px] leading-relaxed text-os-sub underline-offset-2 hover:underline"
            >
                {{ kiokuLetterPreviewLabel(latest) }}
                <span class="ml-1 font-bold text-os-kioku">確認する</span>
            </Link>
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
                    <span>{{
                        letter.cadence === 'daily' && letter.delivery_date
                            ? kiokuLetterTitleLabel(letter)
                            : kiokuLetterWeekLabel(letter.week_start)
                    }}</span>
                    <span>{{ kiokuLetterPreviewLabel(letter) }}</span>
                </Link>
            </div>
        </section>

        <!-- Test letters are isolated from the live frame. -->
        <section
            v-if="tests.length"
            class="rounded-2xl border border-dashed border-os-line bg-os-surface p-4"
        >
            <div
                class="mb-2 text-[11.5px] font-bold tracking-wide text-os-sub"
            >
                [テスト便り]
            </div>
            <ul class="space-y-2">
                <li
                    v-for="letter in tests"
                    :key="letter.id"
                    class="flex items-center gap-2"
                >
                    <Link
                        :href="show.url(letter.id)"
                        class="min-w-0 flex-1 truncate text-[12.5px] font-bold text-os-kioku hover:underline"
                    >
                        {{ kiokuLetterTitleLabel(letter) }}
                    </Link>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-os-sub hover:bg-os-line/40 hover:text-os-ink"
                        aria-label="テスト便りを削除"
                        @click="discardTest(letter.id)"
                    >
                        <Trash2 :size="14" />
                    </button>
                </li>
            </ul>
        </section>
    </div>
</template>
