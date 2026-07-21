<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Mail, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import KiokuLetterPreview from '@/components/kioku/KiokuLetterPreview.vue';
import {
    groupKiokuLetterHistory,
    kiokuLetterPreviewLabel,
    kiokuLetterTitleLabel,
} from '@/lib/kiokuLetter.mjs';
import { destroy, show } from '@/routes/kioku/letters';
import type {
    KiokuLetterScheduleSummary,
    KiokuLetterSummary,
} from '@/types/kiokuLetter';

interface Props {
    letters: KiokuLetterSummary[];
    testLetters?: KiokuLetterSummary[];
    letterSchedule?: KiokuLetterScheduleSummary | null;
}

const props = defineProps<Props>();

const featured = computed(() => {
    const unevaluated = props.letters.find(
        (letter) =>
            !['empty', 'failed', 'evaluated', 'generating'].includes(
                letter.status,
            ),
    );

    return unevaluated ?? props.letters[0] ?? null;
});

const historyLetters = computed(() => {
    if (!featured.value) {
        return props.letters;
    }

    return props.letters.filter((letter) => letter.id !== featured.value?.id);
});

const historyGroups = computed(() =>
    groupKiokuLetterHistory(historyLetters.value),
);

const tests = computed(() => props.testLetters ?? []);
const schedule = computed(() => props.letterSchedule ?? null);

function discardTest(id: string): void {
    router.delete(destroy.url(id), { preserveScroll: true });
}

defineOptions({
    layout: {
        title: 'キオク',
        subtitle: 'キオク便り',
    },
});
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-5">
        <Head title="キオク便り — キオク" />

        <header class="space-y-1.5">
            <h2
                class="flex items-center gap-2 font-serif text-2xl tracking-[0.06em] text-os-ink"
            >
                <Mail :size="22" class="text-os-kioku" />
                キオク便り
            </h2>
            <p class="text-[13px] leading-relaxed text-os-sub">
                今日の便りを読み、過去の便りを振り返り、HIT / MISS
                などの評価を続けます。
            </p>
        </header>

        <KiokuLetterPreview
            :letters="featured ? [featured] : []"
            :letter-schedule="schedule"
            :show-all-link="false"
            :compact-past-limit="0"
        />

        <section
            v-if="schedule && ['paused', 'halted', 'active', 'completed'].includes(schedule.state)"
            class="rounded-2xl border border-os-line bg-os-surface px-4 py-3 text-[12.5px] leading-relaxed text-os-sub"
        >
            <span class="font-bold text-os-ink">実験状況</span>
            ·
            <template v-if="schedule.state === 'active'">配信中</template>
            <template v-else-if="schedule.state === 'paused'"
                >一時停止（未開封が続いたため）</template
            >
            <template v-else-if="schedule.state === 'halted'"
                >停止（sensitive 判定のため）</template
            >
            <template v-else>完了</template>
            <span v-if="schedule.consecutive_unopened > 0" class="ml-1">
                · 連続未開封 {{ schedule.consecutive_unopened }}
            </span>
        </section>

        <section class="space-y-2">
            <h3 class="text-[12px] font-bold tracking-wide text-os-sub">
                過去の live 便り
            </h3>

            <div
                v-if="historyGroups.length === 0"
                class="rounded-2xl border border-os-line bg-os-kioku-paper px-4 py-6 text-center text-[13px] text-os-sub"
            >
                まだ過去の便りはありません。
            </div>

            <template v-for="(group, index) in historyGroups" :key="index">
                <div
                    v-if="group.type === 'empty_run'"
                    class="rounded-xl border border-os-line/80 bg-os-kioku-paper/70 px-4 py-3 text-[12.5px] text-os-sub"
                >
                    empty の日が {{ group.count }}日分あります。
                    <span class="ml-1 text-[11.5px]">
                        （無理に届ける記憶がなかった日）
                    </span>
                </div>
                <Link
                    v-else
                    :href="show.url(group.letter.id)"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-os-line bg-os-kioku-paper px-4 py-3 text-[12.5px] transition-colors hover:bg-os-kioku-soft"
                >
                    <span class="font-medium text-os-ink">{{
                        kiokuLetterTitleLabel(group.letter)
                    }}</span>
                    <span class="text-os-sub">{{
                        kiokuLetterPreviewLabel(group.letter)
                    }}</span>
                </Link>
            </template>
        </section>

        <section
            v-if="tests.length"
            class="rounded-2xl border border-dashed border-os-line bg-os-surface p-4"
        >
            <div
                class="mb-2 text-[11.5px] font-bold tracking-wide text-os-sub"
            >
                [テスト便り] 通常履歴とは分離されています
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
