<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ChevronRight, Mail, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    KIOKU_LETTER_FAILED_MESSAGE,
    KIOKU_LETTER_GROWING_MESSAGE,
    KIOKU_LETTER_HALTED_MESSAGE,
    KIOKU_LETTER_PAUSED_MESSAGE,
    kiokuLetterCharacterMeta,
    kiokuLetterHomeMode,
    kiokuLetterPreviewLabel,
    kiokuLetterTitleLabel,
    kiokuLetterWeekLabel,
} from '@/lib/kiokuLetter.mjs';
import { destroy, index as lettersIndex, show } from '@/routes/kioku/letters';
import type {
    KiokuLetterScheduleSummary,
    KiokuLetterSummary,
} from '@/types/kiokuLetter';

const props = withDefaults(
    defineProps<{
        letters: KiokuLetterSummary[];
        testLetters?: KiokuLetterSummary[];
        letterSchedule?: KiokuLetterScheduleSummary | null;
        showAllLink?: boolean;
        compactPastLimit?: number;
    }>(),
    {
        testLetters: () => [],
        letterSchedule: null,
        showAllLink: true,
        compactPastLimit: 3,
    },
);

const latest = computed(() => props.letters[0] ?? null);
const past = computed(() =>
    props.letters.slice(1, 1 + props.compactPastLimit),
);
const homeMode = computed(() =>
    kiokuLetterHomeMode(latest.value, props.letterSchedule),
);
const tests = computed(() => props.testLetters ?? []);
const characterName = computed(() =>
    latest.value
        ? kiokuLetterCharacterMeta(latest.value.character_variant).name
        : null,
);

function discardTest(id: string): void {
    router.delete(destroy.url(id), { preserveScroll: true });
}

function dateLabel(letter: KiokuLetterSummary): string {
    return letter.cadence === 'daily' && letter.delivery_date
        ? kiokuLetterTitleLabel(letter)
        : kiokuLetterWeekLabel(letter.week_start);
}
</script>

<template>
    <div class="space-y-3">
        <section
            class="rounded-2xl border border-os-line bg-os-kioku-paper p-4 shadow-[0_1px_3px_rgba(43,41,36,0.05)] sm:p-5"
        >
            <div
                class="mb-3 flex items-center gap-1.5 text-[12px] font-bold tracking-wide text-os-kioku"
            >
                <Mail :size="14" />
                今日のキオク便り
            </div>

            <template v-if="homeMode === 'none'">
                <p class="text-[13px] leading-relaxed text-os-sub">
                    {{ KIOKU_LETTER_GROWING_MESSAGE }}
                </p>
            </template>

            <template v-else-if="homeMode === 'growing'">
                <p class="text-[13px] leading-relaxed text-os-sub">
                    {{ KIOKU_LETTER_GROWING_MESSAGE }}
                </p>
            </template>

            <template v-else-if="homeMode === 'schedule_halted'">
                <p class="text-[13px] leading-relaxed text-os-ink">
                    {{ KIOKU_LETTER_HALTED_MESSAGE }}
                </p>
            </template>

            <template v-else-if="homeMode === 'schedule_paused'">
                <p class="text-[13px] leading-relaxed text-os-ink">
                    {{ KIOKU_LETTER_PAUSED_MESSAGE }}
                </p>
            </template>

            <template v-else-if="homeMode === 'failed' && latest">
                <p class="text-[12px] text-os-sub">
                    {{ dateLabel(latest) }}
                </p>
                <p class="mt-1.5 text-[13px] leading-relaxed text-os-ink">
                    {{ KIOKU_LETTER_FAILED_MESSAGE }}
                </p>
            </template>

            <template v-else-if="homeMode === 'halted' && latest">
                <p class="text-[12px] text-os-sub">
                    {{ dateLabel(latest) }}
                </p>
                <p class="mt-1.5 text-[13px] leading-relaxed text-os-ink">
                    {{ KIOKU_LETTER_HALTED_MESSAGE }}
                </p>
                <Link
                    :href="show.url(latest.id)"
                    class="mt-3 inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-os-kioku px-4 text-[13.5px] font-bold text-white shadow-[0_3px_10px_rgba(62,86,136,0.28)] hover:bg-os-kioku/90"
                >
                    便りを読む
                </Link>
            </template>

            <template v-else-if="homeMode === 'empty' && latest">
                <p class="text-[12px] text-os-sub">
                    {{ dateLabel(latest) }}
                </p>
                <p class="mt-1.5 text-[13px] leading-relaxed text-os-sub">
                    {{ kiokuLetterPreviewLabel(latest) }}
                </p>
                <Link
                    :href="show.url(latest.id)"
                    class="mt-3 inline-flex text-[12.5px] font-bold text-os-kioku underline-offset-2 hover:underline"
                >
                    確認する
                </Link>
            </template>

            <template v-else-if="latest">
                <div class="space-y-2">
                    <p class="text-[12px] text-os-sub">
                        {{ dateLabel(latest) }}
                        <span v-if="characterName" class="ml-2"
                            >· {{ characterName }}</span
                        >
                    </p>
                    <p
                        v-if="latest.intro"
                        class="line-clamp-2 text-[13.5px] leading-relaxed text-os-ink"
                    >
                        {{ latest.intro }}
                    </p>
                    <p class="text-[12px] text-os-sub">
                        <template v-if="homeMode === 'done'">
                            評価済み · {{ latest.item_count }}件
                        </template>
                        <template v-else>
                            {{ latest.item_count }}件の記憶
                        </template>
                    </p>
                    <Link
                        :href="show.url(latest.id)"
                        class="mt-1 flex h-11 items-center justify-center gap-2 rounded-xl bg-os-kioku text-[13.5px] font-bold text-white shadow-[0_3px_10px_rgba(62,86,136,0.28)] transition-colors hover:bg-os-kioku/90 motion-reduce:transition-none"
                    >
                        便りを読む
                    </Link>
                    <p
                        v-if="homeMode === 'done' || homeMode === 'in_progress'"
                        class="text-[11.5px] text-os-sub"
                    >
                        {{ kiokuLetterPreviewLabel(latest) }}
                    </p>
                </div>
            </template>

            <div v-if="past.length" class="mt-3 space-y-0.5 border-t border-os-line pt-2.5">
                <Link
                    v-for="letter in past"
                    :key="letter.id"
                    :href="show.url(letter.id)"
                    class="flex items-center justify-between gap-2 py-1.5 text-[11.5px] text-os-sub hover:text-os-ink"
                >
                    <span class="min-w-0 truncate">{{ dateLabel(letter) }}</span>
                    <span class="shrink-0">{{
                        kiokuLetterPreviewLabel(letter)
                    }}</span>
                </Link>
            </div>

            <div v-if="showAllLink" class="mt-3">
                <Link
                    :href="lettersIndex()"
                    class="inline-flex items-center gap-1 text-[12.5px] font-bold text-os-kioku hover:underline"
                >
                    キオク便りをすべて見る
                    <ChevronRight :size="14" />
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
