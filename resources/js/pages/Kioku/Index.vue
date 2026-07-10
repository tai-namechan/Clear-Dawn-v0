<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Brain, Plus, RefreshCw, Search, Send, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import KiokuNav from '@/components/kioku/KiokuNav.vue';
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
        subtitle: '記憶の保存・検索・想起',
    },
});
</script>

<template>
    <div class="space-y-5">
        <Head title="キオク" />

        <!-- Brand header (soft Console tone, without "Console" label) -->
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#2B2836] font-serif text-[1.35rem] leading-none text-white shadow-sm"
                    aria-hidden="true"
                >
                    K
                </div>
                <div>
                    <h2
                        class="font-serif text-[1.55rem] leading-none font-normal tracking-[0.04em] text-os-ink"
                    >
                        キオク
                    </h2>
                    <p class="mt-1 text-xs text-os-sub">
                        記憶の保存・検索・想起
                    </p>
                </div>
            </div>
            <div class="text-xs text-os-faint">{{ totalCount }}件の記憶</div>
        </header>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <KiokuNav active="home" />
        </div>

        <div
            class="grid gap-5 lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)] lg:items-start"
        >
            <aside class="space-y-3.5 lg:sticky lg:top-5">
                <!-- Soft paper capture CTA -->
                <section
                    class="rounded-2xl border border-[#e6e0d4] bg-os-kioku-paper px-4 py-4"
                >
                    <div class="mb-1 text-center text-sm font-semibold text-os-ink">
                        <span class="mr-1 text-os-kioku">+</span>
                        メモを今すぐ保存する
                    </div>
                    <p class="mb-3 text-center text-[11.5px] leading-relaxed text-os-sub">
                        思いついたこと、知識、気づきをすぐに記録
                    </p>
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
                            placeholder="エラー、考え、URL… 貼るだけ。整理はAIが後から。"
                            class="w-full resize-y rounded-xl border border-[#e0dbd0] bg-white/80 px-3.5 py-3 text-[13.5px] leading-relaxed text-os-ink outline-none placeholder:text-os-faint focus-visible:ring-2 focus-visible:ring-os-kioku/30"
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
                            class="h-10 w-full gap-2 rounded-xl bg-os-kioku text-[13px] font-semibold text-white hover:bg-os-kioku/90"
                            :disabled="processing || !draft.trim()"
                            :class="draft.trim() ? 'opacity-100' : 'opacity-45'"
                        >
                            <Send :size="14" />
                            保存する
                        </Button>
                    </Form>
                </section>

                <section class="rounded-2xl border border-os-line bg-white px-4 py-3.5">
                    <div
                        class="mb-2.5 text-[11px] font-semibold tracking-wide text-os-faint"
                    >
                        種別でしぼる
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-xs transition-colors"
                            :class="
                                selectedTypes.length === 0
                                    ? 'border-os-kioku/30 bg-os-kioku-soft font-semibold text-os-kioku'
                                    : 'border-os-line bg-[#F7F6FA] text-os-sub'
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
                                    ? 'font-semibold'
                                    : 'border-os-line bg-[#F7F6FA] text-os-sub'
                            "
                            :style="
                                selectedTypes.includes(key)
                                    ? {
                                          background: MEMORY_TYPES[key].bg,
                                          color: MEMORY_TYPES[key].color,
                                          borderColor: MEMORY_TYPES[key].color + '40',
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

                <section class="rounded-2xl border border-os-line bg-white px-4 py-3.5">
                    <div
                        class="mb-2.5 text-[11px] font-semibold tracking-wide text-os-faint"
                    >
                        取り込み元
                    </div>
                    <div
                        v-for="(meta, key) in SOURCE_TYPES"
                        :key="key"
                        class="flex items-center gap-2 border-b border-os-line/80 py-1.5 text-[12.5px] last:border-0"
                        :class="meta.muted ? 'opacity-45' : ''"
                    >
                        <component :is="meta.icon" :size="13" class="text-os-sub" />
                        <span class="flex-1 text-os-ink">{{ meta.label }}</span>
                        <span class="font-mono text-xs text-os-faint">{{
                            sourceCounts[key as SourceTypeKey] || 0
                        }}</span>
                    </div>
                </section>
            </aside>

            <section class="space-y-3">
                <div
                    class="flex items-center gap-2.5 rounded-2xl border border-os-line bg-white px-4 py-3"
                >
                    <Search :size="16" class="text-os-faint" />
                    <input
                        v-model="q"
                        placeholder="キーワード、タグ、日付で検索…"
                        class="min-w-0 flex-1 bg-transparent text-[13.5px] text-os-ink outline-none placeholder:text-os-faint"
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
                        class="gap-1"
                        @click="reload"
                    >
                        <RefreshCw :size="13" />
                        更新
                    </Button>
                </div>

                <div class="flex items-baseline justify-between gap-2 px-0.5">
                    <h3 class="text-sm font-semibold text-os-ink">最近の記憶</h3>
                    <span class="text-xs text-os-kioku">{{ memories.length }}件</span>
                </div>

                <div
                    v-if="memories.length === 0"
                    class="rounded-2xl border border-dashed border-os-line bg-white/70 p-10 text-center"
                >
                    <Brain :size="24" class="mx-auto mb-2 text-os-faint" />
                    <p class="text-[13px] leading-relaxed text-os-sub">
                        {{
                            q
                                ? `「${q}」に一致する記憶はありません。`
                                : 'まだ記憶がありません。左から保存してみてください。'
                        }}
                    </p>
                </div>

                <div class="space-y-2.5">
                    <MemoryCard
                        v-for="memory in memories"
                        :key="memory.id"
                        :memory="memory"
                    />
                </div>

                <p
                    class="mx-auto max-w-md pt-4 text-center text-[11.5px] leading-relaxed text-os-faint"
                >
                    大切な情報や学びを蓄積し、必要なときにすぐ取り出せる。<br />
                    あなたの記憶を、未来の行動に変換していきます。
                </p>
            </section>
        </div>
    </div>
</template>
