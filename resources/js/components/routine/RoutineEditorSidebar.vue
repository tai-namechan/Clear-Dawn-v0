<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, History, Layers } from '@lucide/vue';
import { computed } from 'vue';
import type { Routine, RoutineEditor } from '@/types/routine';

type FlowPhase = 'name' | 'steps' | 'ready';

interface Props {
    routine: RoutineEditor;
    otherRoutines?: Routine[];
    flowPhase?: FlowPhase;
    applyingToToday?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    otherRoutines: () => [],
    flowPhase: 'ready',
    applyingToToday: false,
});

const emit = defineEmits<{
    'apply-to-today': [];
}>();

const recommended = computed(() =>
    props.otherRoutines
        .filter(
            (item) =>
                props.routine.id === null || item.id !== props.routine.id,
        )
        .slice(0, 3),
);

const stepCount = computed(() => props.routine.steps?.length ?? 0);

const helpLines = computed(() => {
    if (props.flowPhase === 'name') {
        return [
            'いまは①です。名前を入力して「① 名前を保存して次へ」を押してください。',
            'この時点ではまだルーティンは作られません。',
        ];
    }

    if (props.flowPhase === 'steps') {
        return [
            'いまは②です。「ステップを追加」→ 内容入力 → 「このステップを保存」です。',
            'ステップは1件ごとに保存します。必要なだけ繰り返してください。',
        ];
    }

    return [
        'いまは③です。「今日やるに登録して進む」で今日の予定に載せます。',
        `現在 ${stepCount.value} ステップです。同じルーティンを複数回登録しても構いません。`,
    ];
});
</script>

<template>
    <aside class="flex flex-col gap-4">
        <section aria-label="次にやること" class="cd-panel px-4 py-4">
            <h2 class="font-sans text-sm font-semibold text-cd-ink">
                次にやること
            </h2>
            <ul class="mt-3 space-y-2 font-sans text-sm">
                <li v-if="flowPhase === 'ready'">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-left font-medium text-primary transition-colors hover:bg-primary/10 disabled:opacity-60"
                        :disabled="applyingToToday"
                        @click="emit('apply-to-today')"
                    >
                        <span class="inline-flex items-center gap-2">
                            <Layers :size="15" :stroke-width="1.6" />
                            {{
                                applyingToToday
                                    ? '登録中…'
                                    : '今日やるに登録して進む'
                            }}
                        </span>
                        <ChevronRight :size="14" :stroke-width="1.6" />
                    </button>
                </li>
                <li
                    v-else
                    class="rounded-lg border border-dashed border-cd-line px-3 py-2 text-cd-ink-muted"
                >
                    <p class="font-medium text-cd-ink">
                        {{
                            flowPhase === 'name'
                                ? '① 名前を保存する'
                                : '② ステップを保存する'
                        }}
                    </p>
                    <p class="mt-1 text-xs">
                        {{
                            flowPhase === 'name'
                                ? '基本情報のボタンから進めます'
                                : '「ステップを追加」ダイアログで保存します'
                        }}
                    </p>
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
                手順
            </h2>
            <ol class="mt-2 space-y-2 font-sans text-xs leading-relaxed text-cd-ink-muted">
                <li
                    :class="{
                        'font-medium text-cd-ink': flowPhase === 'name',
                    }"
                >
                    ① 名前を入力して保存
                </li>
                <li
                    :class="{
                        'font-medium text-cd-ink': flowPhase === 'steps',
                    }"
                >
                    ② 「ステップを追加」でやることを登録（1件ずつ保存）
                </li>
                <li
                    :class="{
                        'font-medium text-cd-ink': flowPhase === 'ready',
                    }"
                >
                    ③ 今日やるに登録
                </li>
            </ol>
            <p
                v-for="(line, index) in helpLines"
                :key="index"
                class="mt-2 font-sans text-xs leading-relaxed text-cd-ink-muted"
            >
                {{ line }}
            </p>
        </section>
    </aside>
</template>
