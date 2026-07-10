<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Brain, Plus, RefreshCw, Search, Send, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import MemoryCard from '@/components/kioku/MemoryCard.vue';
import { Button } from '@/components/ui/button';
import {
    MEMORY_TYPES,
    SOURCE_TYPES,
    type MemoryTypeKey,
    type SourceTypeKey,
} from '@/lib/kiokuMeta';
import { home } from '@/routes/kioku';
import { store } from '@/routes/kioku/memories';
import type { KiokuMemory, MemoryTypeOption } from '@/types/kioku';

interface Props {
    memories: KiokuMemory[];
    filters: { q: string | null; types: string[] };
    memoryTypes: MemoryTypeOption[];
    typeCounts: Record<string, number>;
    sourceCounts: Record<string, number>;
    totalCount: number;
}

const props = defineProps<Props>();

const q = ref(props.filters.q ?? '');
const selectedTypes = ref<string[]>([...props.filters.types]);
const draft = ref('');

watch(
    () => props.filters,
    (filters) => {
        q.value = filters.q ?? '';
        selectedTypes.value = [...filters.types];
    },
);

const hasEnriching = computed(() =>
    props.memories.some((m) => m.status === 'enriching' || m.status === 'captured'),
);

const visibleTypeKeys = computed(() =>
    (Object.keys(MEMORY_TYPES) as MemoryTypeKey[]).filter(
        (key) => (props.typeCounts[key] ?? 0) > 0 || selectedTypes.value.includes(key),
    ),
);

function applyFilters(): void {
    router.get(
        home.url({
            query: {
                q: q.value || undefined,
                types: selectedTypes.value.length ? selectedTypes.value : undefined,
            },
        }),
        {},
        { preserveState: true, replace: true },
    );
}

function toggleType(key: string): void {
    if (selectedTypes.value.includes(key)) {
        selectedTypes.value = selectedTypes.value.filter((t) => t !== key);
    } else {
        selectedTypes.value = [...selectedTypes.value, key];
    }
    applyFilters();
}

function clearTypes(): void {
    selectedTypes.value = [];
    applyFilters();
}

function reload(): void {
    router.reload({ only: ['memories', 'typeCounts', 'sourceCounts', 'totalCount'] });
}

defineOptions({
    layout: {
        title: 'キオク',
        subtitle: '経験を、失わない。 — 過去を思い出す場所',
    },
});
</script>

<template>
    <div class="space-y-4">
        <Head title="キオク" />

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs tracking-wide text-os-sub">
                経験を、失わない。 — 過去を思い出す場所
            </p>
            <div class="text-xs text-os-faint">{{ totalCount }}件の記憶</div>
        </div>

        <div
            class="grid gap-5 lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)] lg:items-start"
        >
            <aside class="space-y-3.5 lg:sticky lg:top-5">
                <section
                    class="rounded-2xl border border-os-kioku/25 bg-os-kioku-paper p-4 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
                >
                    <div
                        class="mb-2.5 flex items-center gap-1.5 text-xs font-bold text-os-kioku"
                    >
                        <Plus :size="14" />
                        なんでも、まずここへ
                    </div>
                    <Form
                        v-bind="store.form()"
                        class="space-y-2.5"
                        #default="{ processing }"
                        @success="draft = ''"
                    >
                        <textarea
                            v-model="draft"
                            name="raw_content"
                            rows="4"
                            required
                            placeholder="エラーメッセージ、考えたこと、URL…&#10;貼るだけ。整理はAIが後からやります。&#10;（Ctrl/⌘+Enterで保存）"
                            class="w-full resize-y rounded-xl border border-os-line bg-os-kioku-bg px-3.5 py-3 text-[13.5px] leading-relaxed text-os-ink outline-none placeholder:text-[#B3AC99] focus-visible:ring-2 focus-visible:ring-os-kioku/35"
                            @keydown.meta.enter.prevent="
                                ($event.target as HTMLTextAreaElement).form?.requestSubmit()
                            "
                            @keydown.ctrl.enter.prevent="
                                ($event.target as HTMLTextAreaElement).form?.requestSubmit()
                            "
                        />
                        <input type="hidden" name="source_type" value="manual" />
                        <Button
                            type="submit"
                            class="h-11 w-full gap-2 rounded-xl bg-os-kioku text-[13.5px] font-bold text-white shadow-[0_3px_10px_rgba(62,86,136,0.28)] hover:bg-os-kioku/90"
                            :disabled="processing || !draft.trim()"
                            :class="draft.trim() ? 'opacity-100' : 'opacity-40'"
                        >
                            <Send :size="15" />
                            保存（AIが自動整理）
                        </Button>
                    </Form>
                </section>

                <section
                    class="rounded-2xl border border-os-line bg-os-kioku-paper p-4 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
                >
                    <div
                        class="mb-2.5 text-[11.5px] font-bold tracking-wide text-os-faint"
                    >
                        種別でしぼる
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-xs transition-colors"
                            :class="
                                selectedTypes.length === 0
                                    ? 'border-os-kioku/40 bg-os-kioku-soft font-bold text-os-kioku'
                                    : 'border-os-line bg-[#F0ECE0] text-os-sub'
                            "
                            @click="clearTypes"
                        >
                            すべて {{ totalCount }}
                        </button>
                        <button
                            v-for="key in visibleTypeKeys"
                            :key="key"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-xs transition-colors"
                            :class="
                                selectedTypes.includes(key)
                                    ? 'font-bold'
                                    : 'border-os-line bg-[#F0ECE0] text-os-sub'
                            "
                            :style="
                                selectedTypes.includes(key)
                                    ? {
                                          background: MEMORY_TYPES[key].bg,
                                          color: MEMORY_TYPES[key].color,
                                          borderColor: MEMORY_TYPES[key].color + '55',
                                      }
                                    : undefined
                            "
                            @click="toggleType(key)"
                        >
                            <component :is="MEMORY_TYPES[key].icon" :size="12" />
                            {{ MEMORY_TYPES[key].label }}
                            {{ typeCounts[key] || 0 }}
                        </button>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-os-line bg-os-kioku-paper p-4 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
                >
                    <div
                        class="mb-2.5 text-[11.5px] font-bold tracking-wide text-os-faint"
                    >
                        取り込み元
                    </div>
                    <div
                        v-for="(meta, key) in SOURCE_TYPES"
                        :key="key"
                        class="flex items-center gap-2 border-b border-os-line py-1.5 text-[12.5px] last:border-0"
                        :class="meta.muted ? 'opacity-45' : ''"
                    >
                        <component :is="meta.icon" :size="13" class="text-os-sub" />
                        <span class="flex-1 text-os-ink">{{ meta.label }}</span>
                        <span class="font-mono text-xs text-os-faint">{{
                            sourceCounts[key as SourceTypeKey] || 0
                        }}</span>
                    </div>
                    <p class="mt-2.5 text-[11px] leading-relaxed text-os-faint">
                        ヨユウ・Clear Dawnからの自動保存とSlack連携は、本実装ではイベント/コネクタ経由になります。
                    </p>
                </section>
            </aside>

            <section class="space-y-3.5">
                <div
                    class="flex items-center gap-2.5 rounded-2xl border border-os-line bg-os-kioku-paper px-4 py-3 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
                >
                    <Search :size="16" class="text-os-faint" />
                    <input
                        v-model="q"
                        placeholder="記憶を検索（例: Vite / 転職 / ヨガ）"
                        class="min-w-0 flex-1 bg-transparent text-[13.5px] text-os-ink outline-none placeholder:text-[#B3AC99]"
                        @keydown.enter.prevent="applyFilters"
                    />
                    <button
                        v-if="q"
                        type="button"
                        class="text-os-faint"
                        @click="
                            q = '';
                            applyFilters();
                        "
                    >
                        <X :size="14" />
                    </button>
                    <Button
                        v-if="hasEnriching"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="gap-1 border-os-line"
                        @click="reload"
                    >
                        <RefreshCw :size="13" />
                        更新
                    </Button>
                </div>

                <div
                    v-if="memories.length === 0"
                    class="rounded-2xl border border-os-line bg-os-kioku-paper p-9 text-center shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
                >
                    <Brain :size="26" class="mx-auto mb-2.5 text-os-faint" />
                    <p class="text-[13px] leading-relaxed text-os-sub">
                        {{
                            q
                                ? `「${q}」に一致する記憶はありません。`
                                : 'まだ記憶がありません。左の保存ボックスからどうぞ。'
                        }}
                    </p>
                </div>

                <MemoryCard
                    v-for="memory in memories"
                    :key="memory.id"
                    :memory="memory"
                />

                <p class="pt-2 text-center text-[11px] leading-relaxed text-os-faint">
                    保存は即時、AI整理（分類・要約・構造化）は非同期。Laravel Queue
                    で同じ流れです。
                </p>
            </section>
        </div>
    </div>
</template>
