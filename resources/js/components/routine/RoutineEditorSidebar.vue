<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarPlus, ChevronRight, Clock3, History, Pencil } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { RoutineEditor } from '@/types/routine';

type FlowPhase = 'name' | 'steps' | 'ready';

interface Props {
    routine: RoutineEditor;
    flowPhase?: FlowPhase;
    applyingToToday?: boolean;
    categoryLabel?: string;
    durationLabel?: string;
}

const props = withDefaults(defineProps<Props>(), {
    flowPhase: 'ready',
    applyingToToday: false,
    categoryLabel: '—',
    durationLabel: '—',
});

const emit = defineEmits<{
    'apply-to-today': [];
    'edit-basics': [];
}>();

const stepCount = computed(() => props.routine.steps?.length ?? 0);
</script>

<template>
    <aside class="flex flex-col gap-4 xl:sticky xl:top-4 xl:self-start">
        <section aria-label="ルーティン概要" class="cd-panel overflow-hidden">
            <div class="border-b border-cd-line px-4 py-3.5">
                <h2 class="font-sans text-sm font-semibold text-cd-ink">
                    ルーティン概要
                </h2>
            </div>

            <div class="grid grid-cols-3 divide-x divide-cd-line px-2 py-4 text-center xl:grid-cols-1 xl:divide-x-0 xl:divide-y">
                <div class="px-2 xl:py-3">
                    <p class="font-sans text-xl font-semibold text-cd-ink">
                        {{ stepCount }}
                    </p>
                    <p class="mt-0.5 font-sans text-[11px] text-cd-ink-muted">
                        STEP
                    </p>
                </div>
                <div class="px-2 xl:py-3">
                    <p class="truncate font-sans text-sm font-semibold text-cd-ink">
                        {{ durationLabel }}
                    </p>
                    <p class="mt-0.5 font-sans text-[11px] text-cd-ink-muted">
                        予定時間
                    </p>
                </div>
                <div class="px-2 xl:py-3">
                    <p class="truncate font-sans text-sm font-semibold text-cd-ink">
                        {{ categoryLabel }}
                    </p>
                    <p class="mt-0.5 font-sans text-[11px] text-cd-ink-muted">
                        メイン分類
                    </p>
                </div>
            </div>

            <div class="space-y-2 border-t border-cd-line p-4">
                <Button
                    v-if="flowPhase === 'ready'"
                    type="button"
                    class="w-full"
                    :disabled="applyingToToday"
                    @click="emit('apply-to-today')"
                >
                    <CalendarPlus :size="16" :stroke-width="1.7" />
                    {{ applyingToToday ? '登録中…' : '今日に追加' }}
                    <ChevronRight :size="15" :stroke-width="1.7" />
                </Button>
                <div
                    v-else
                    class="rounded-lg border border-dashed border-cd-line px-3 py-2.5 font-sans text-xs text-cd-ink-muted"
                >
                    {{
                        flowPhase === 'name'
                            ? 'まず基本情報を保存してください。'
                            : 'ステップを1件以上追加すると今日に載せられます。'
                    }}
                </div>

                <Button
                    type="button"
                    variant="outline"
                    class="w-full justify-start"
                    @click="emit('edit-basics')"
                >
                    <Pencil :size="15" :stroke-width="1.6" />
                    基本情報を編集
                </Button>
                <Button type="button" variant="ghost" class="w-full justify-between" as-child>
                    <Link href="/history">
                        <span class="inline-flex items-center gap-2">
                            <History :size="15" :stroke-width="1.6" />
                            実行履歴
                        </span>
                        <ChevronRight :size="14" :stroke-width="1.6" />
                    </Link>
                </Button>
            </div>
        </section>

        <section class="hidden rounded-xl border border-cd-line/70 bg-white/45 px-4 py-3 xl:block">
            <div class="flex items-center gap-2 font-sans text-xs text-cd-ink-muted">
                <Clock3 :size="14" :stroke-width="1.5" />
                並べ替えはドラッグ、または上下ボタンで行えます。
            </div>
        </section>
    </aside>
</template>
