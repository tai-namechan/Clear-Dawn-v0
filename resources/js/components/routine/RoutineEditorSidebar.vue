<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, History, Layers } from '@lucide/vue';
import { computed } from 'vue';
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
        <section aria-label="次にやること" class="cd-panel px-4 py-4">
            <h2 class="font-sans text-sm font-semibold text-cd-ink">
                次にやること
            </h2>
            <ul class="mt-3 space-y-2 font-sans text-sm">
                <li>
                    <Link
                        href="/today"
                        class="flex items-center justify-between gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 font-medium text-primary transition-colors hover:bg-primary/10"
                    >
                        <span class="inline-flex items-center gap-2">
                            <Layers :size="15" :stroke-width="1.6" />
                            今日やるへ進む
                        </span>
                        <ChevronRight :size="14" :stroke-width="1.6" />
                    </Link>
                </li>
                <li>
                    <Link
                        href="/history"
                        class="flex items-center justify-between gap-2 text-cd-ink-muted transition-colors hover:text-primary"
                    >
                        <span class="inline-flex items-center gap-2">
                            <History :size="15" :stroke-width="1.6" />
                            履歴を見る
                        </span>
                        <ChevronRight :size="14" :stroke-width="1.6" />
                    </Link>
                </li>
            </ul>
        </section>

        <section
            v-if="recommended.length"
            aria-label="おすすめのルーティン"
            class="cd-panel px-4 py-4"
        >
            <h2 class="font-sans text-sm font-semibold text-cd-ink">
                おすすめのルーティン
            </h2>
            <ul class="mt-3 space-y-2">
                <li v-for="item in recommended" :key="item.id">
                    <Link
                        :href="`/routines/${item.id}`"
                        class="block rounded-xl border border-cd-line bg-white px-3 py-2 transition-colors hover:border-primary/40 hover:bg-primary/5"
                    >
                        <p class="font-sans text-sm font-semibold text-cd-ink">
                            {{ item.name }}
                        </p>
                        <p class="mt-0.5 font-sans text-xs text-cd-ink-muted">
                            {{ item.steps_count ?? 0 }} ステップ
                        </p>
                    </Link>
                </li>
            </ul>
        </section>

        <section aria-label="ヘルプ" class="cd-panel px-4 py-4">
            <h2 class="font-sans text-sm font-semibold text-cd-ink">
                ヘルプ
            </h2>
            <p class="mt-2 font-sans text-xs leading-relaxed text-cd-ink-muted">
                ① 「ステップを追加」でやることを登録 → ② 今日やる → ③
                実行画面で1つずつ完了。 現在 {{ stepCount }} ステップです。
            </p>
            <p class="mt-2 font-sans text-xs leading-relaxed text-cd-ink-muted">
                ※ ステップの中身（スクワットなど）は「ステップを追加」時にその場で作れます。別画面の整理用一覧は不要なら使わなくて大丈夫です。
            </p>
        </section>
    </aside>
</template>
