<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, History, Layers, ListChecks } from '@lucide/vue';
import { computed } from 'vue';
import { formatDurationSeconds } from '@/lib/routineConstants';
import type { Routine, RoutineEditor } from '@/types/routine';

interface Props {
    routine: RoutineEditor;
    otherRoutines?: Routine[];
}

const props = withDefaults(defineProps<Props>(), {
    otherRoutines: () => [],
});

const recommended = computed(() =>
    props.otherRoutines
        .filter((item) => item.id !== props.routine.id)
        .slice(0, 3),
);

const stepCount = computed(() => props.routine.steps?.length ?? 0);
</script>

<template>
    <aside class="flex flex-col gap-4">
        <section
            aria-label="データのつながり"
            class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface px-4 py-4"
        >
            <h2 class="font-serif text-sm tracking-[0.12em] text-cd-ink">
                データのつながり
            </h2>
            <ul class="mt-3 space-y-2 font-sans text-sm">
                <li>
                    <Link
                        href="/routine-items"
                        class="flex items-center justify-between gap-2 text-cd-ink-muted transition-colors hover:text-cd-ink"
                    >
                        <span class="inline-flex items-center gap-2">
                            <ListChecks :size="15" :stroke-width="1.6" />
                            実施項目ライブラリ
                        </span>
                        <ChevronRight :size="14" :stroke-width="1.6" />
                    </Link>
                </li>
                <li>
                    <Link
                        href="/today"
                        class="flex items-center justify-between gap-2 text-cd-ink-muted transition-colors hover:text-cd-ink"
                    >
                        <span class="inline-flex items-center gap-2">
                            <Layers :size="15" :stroke-width="1.6" />
                            今日の実行プラン
                        </span>
                        <ChevronRight :size="14" :stroke-width="1.6" />
                    </Link>
                </li>
                <li>
                    <Link
                        href="/history"
                        class="flex items-center justify-between gap-2 text-cd-ink-muted transition-colors hover:text-cd-ink"
                    >
                        <span class="inline-flex items-center gap-2">
                            <History :size="15" :stroke-width="1.6" />
                            実行履歴
                        </span>
                        <ChevronRight :size="14" :stroke-width="1.6" />
                    </Link>
                </li>
            </ul>
        </section>

        <section
            v-if="recommended.length"
            aria-label="おすすめのルーティン"
            class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface px-4 py-4"
        >
            <h2 class="font-serif text-sm tracking-[0.12em] text-cd-ink">
                おすすめのルーティン
            </h2>
            <ul class="mt-3 space-y-2">
                <li v-for="item in recommended" :key="item.id">
                    <Link
                        :href="`/routines/${item.id}`"
                        class="block rounded-xl border border-cd-line/60 bg-white/40 px-3 py-2 transition-colors hover:border-cd-line"
                    >
                        <p class="font-serif text-sm tracking-[0.06em] text-cd-ink">
                            {{ item.name }}
                        </p>
                        <p class="mt-0.5 font-sans text-xs text-cd-ink-muted">
                            {{ item.steps_count ?? 0 }} ステップ
                        </p>
                    </Link>
                </li>
            </ul>
        </section>

        <section
            aria-label="ヘルプ"
            class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface px-4 py-4"
        >
            <h2 class="font-serif text-sm tracking-[0.12em] text-cd-ink">
                ヘルプ
            </h2>
            <p class="mt-2 font-sans text-xs leading-relaxed text-cd-ink-muted">
                ルーティンは再利用テンプレートです。ステップを追加してから
                「今日の実行プラン」へ展開し、実行セッションで記録します。
                現在 {{ stepCount }} ステップが登録されています。
            </p>
        </section>
    </aside>
</template>
