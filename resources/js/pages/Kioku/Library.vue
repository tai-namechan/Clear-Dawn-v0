<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Filter, Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import MemoryCard from '@/components/kioku/MemoryCard.vue';
import { Button } from '@/components/ui/button';
import { useKiokuStatusPoll } from '@/composables/useKiokuStatusPoll';
import { MEMORY_TYPES } from '@/lib/kiokuMeta';
import type { MemoryTypeKey } from '@/lib/kiokuMeta';
import {
    buildKiokuHomeQuery,
    groupMemoriesByTag,
    normalizeTagMode,
    toggleTagFilter,
} from '@/lib/kiokuTags.mjs';
import { index as memoriesIndex } from '@/routes/kioku/memories';
import type {
    KiokuHomeFilters,
    KiokuMemory,
    KiokuTagCount,
    KiokuTagMode,
    MemoryTypeOption,
} from '@/types/kioku';

type LibraryViewMode = 'timeline' | 'tags';

interface Props {
    memories: KiokuMemory[];
    filters: KiokuHomeFilters;
    memoryTypes: MemoryTypeOption[];
    typeCounts: Record<string, number>;
    sourceCounts: Record<string, number>;
    tagCounts: KiokuTagCount[];
    totalCount: number;
    transcriptionEnabled: boolean;
}

const props = defineProps<Props>();

const q = ref(props.filters.q ?? '');
const selectedTypes = ref<string[]>([...props.filters.types]);
const selectedTags = ref<string[]>([...(props.filters.tags ?? [])]);
const tagMode = ref<KiokuTagMode>(normalizeTagMode(props.filters.tag_mode));
const viewMode = ref<LibraryViewMode>('timeline');
const filtersOpen = ref(false);

watch(
    () => props.filters,
    (filters) => {
        q.value = filters.q ?? '';
        selectedTypes.value = [...filters.types];
        selectedTags.value = [...(filters.tags ?? [])];
        tagMode.value = normalizeTagMode(filters.tag_mode);
    },
);

const pollableMemories = computed(() =>
    props.memories.filter(
        (memory) =>
            props.transcriptionEnabled ||
            memory.source_type !== 'voice' ||
            memory.transcription_status !== 'pending',
    ),
);

const { timedOut, timeoutMessage } = useKiokuStatusPoll(
    () => pollableMemories.value,
);

function manualReload(): void {
    router.reload({
        only: ['memories', 'typeCounts', 'sourceCounts', 'tagCounts', 'totalCount'],
        preserveUrl: true,
    });
}

const visibleTypeKeys = computed(() =>
    (Object.keys(MEMORY_TYPES) as MemoryTypeKey[]).filter(
        (key) =>
            (props.typeCounts[key] ?? 0) > 0 ||
            selectedTypes.value.includes(key),
    ),
);

/** Candidates come from all owned memories so selection never empties the list. */
const tagCandidates = computed(() => props.tagCounts.slice(0, 20));

const tagGroups = computed(() => groupMemoriesByTag(props.memories));

const hasActiveFilters = computed(
    () =>
        Boolean(q.value) ||
        selectedTypes.value.length > 0 ||
        selectedTags.value.length > 0,
);

const activeFilterChips = computed(() => {
    const chips: Array<{ key: string; label: string; kind: 'q' | 'type' | 'tag' }> =
        [];

    if (q.value) {
        chips.push({ key: 'q', label: `「${q.value}」`, kind: 'q' });
    }

    for (const type of selectedTypes.value) {
        const meta = MEMORY_TYPES[type as MemoryTypeKey];
        chips.push({
            key: `type:${type}`,
            label: meta?.label ?? type,
            kind: 'type',
        });
    }

    for (const tag of selectedTags.value) {
        chips.push({ key: `tag:${tag}`, label: `#${tag}`, kind: 'tag' });
    }

    return chips;
});

function applyFilters(): void {
    router.get(
        memoriesIndex.url({
            query: buildKiokuHomeQuery({
                q: q.value || null,
                types: selectedTypes.value,
                tags: selectedTags.value,
                tagMode: tagMode.value,
            }),
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

function toggleTag(tag: string): void {
    selectedTags.value = toggleTagFilter(selectedTags.value, tag);

    if (selectedTags.value.length < 2) {
        tagMode.value = 'and';
    }

    applyFilters();
}

function setTagMode(mode: KiokuTagMode): void {
    if (tagMode.value === mode) {
        return;
    }

    tagMode.value = mode;
    applyFilters();
}

function removeChip(chip: { key: string; kind: 'q' | 'type' | 'tag' }): void {
    if (chip.kind === 'q') {
        q.value = '';
        applyFilters();

        return;
    }

    if (chip.kind === 'type') {
        const type = chip.key.replace(/^type:/, '');
        selectedTypes.value = selectedTypes.value.filter((t) => t !== type);
        applyFilters();

        return;
    }

    const tag = chip.key.replace(/^tag:/, '');
    toggleTag(tag);
}

function clearAllFilters(): void {
    q.value = '';
    selectedTypes.value = [];
    selectedTags.value = [];
    tagMode.value = 'and';
    applyFilters();
}

defineOptions({
    layout: {
        title: 'キオク',
        subtitle: 'キオクを探す',
    },
});
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-4">
        <Head title="キオクを探す — キオク" />

        <header class="space-y-1.5">
            <h2 class="font-serif text-2xl tracking-[0.06em] text-os-ink">
                キオクを探す
            </h2>
            <p class="text-[13px] leading-relaxed text-os-sub">
                言葉、タグ、種類から、過去に残したキオクを探せます。
            </p>
        </header>

        <div
            v-if="timedOut"
            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-os-line bg-os-kioku-paper px-4 py-3 text-[13px] text-os-sub"
            role="status"
        >
            <p>{{ timeoutMessage }}</p>
            <Button
                type="button"
                variant="outline"
                class="h-9 rounded-xl border-os-kioku/30 text-os-kioku"
                @click="manualReload"
            >
                更新する
            </Button>
        </div>

        <div
            class="rounded-2xl border border-os-line bg-os-kioku-paper px-3 py-3 shadow-[0_1px_3px_rgba(43,41,36,0.05)] sm:px-4"
        >
            <div class="flex items-center gap-2">
                <Search :size="16" class="shrink-0 text-os-faint" />
                <input
                    v-model="q"
                    placeholder="記憶を検索（例: Vite / 転職 / ヨガ）"
                    class="min-w-0 flex-1 bg-transparent text-[13.5px] text-os-ink outline-none placeholder:text-[#B3AC99]"
                    @keydown.enter.prevent="applyFilters"
                />
                <button
                    v-if="q"
                    type="button"
                    class="text-os-sub"
                    aria-label="検索語をクリア"
                    @click="
                        q = '';
                        applyFilters();
                    "
                >
                    <X :size="14" />
                </button>
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 shrink-0 gap-1.5 rounded-xl border-os-line px-3 text-xs text-os-sub lg:hidden"
                    :aria-expanded="filtersOpen"
                    @click="filtersOpen = !filtersOpen"
                >
                    <Filter :size="14" />
                    絞り込み
                </Button>
            </div>

            <div
                v-if="activeFilterChips.length"
                class="mt-2.5 flex flex-wrap gap-1.5"
            >
                <button
                    v-for="chip in activeFilterChips"
                    :key="chip.key"
                    type="button"
                    class="inline-flex max-w-full items-center gap-1 rounded-full border border-os-kioku/35 bg-os-kioku-soft px-2.5 py-1 text-[11.5px] font-bold text-os-kioku"
                    :aria-label="`${chip.label} を解除`"
                    @click="removeChip(chip)"
                >
                    <span class="truncate">{{ chip.label }}</span>
                    <X :size="11" />
                </button>
                <button
                    type="button"
                    class="text-[11.5px] text-os-sub underline-offset-2 hover:underline"
                    @click="clearAllFilters"
                >
                    すべて解除
                </button>
            </div>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-os-line bg-os-kioku-paper shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
            :class="filtersOpen ? 'block' : 'hidden lg:block'"
        >
            <div class="space-y-4 p-4">
                <section>
                    <div
                        class="mb-2 text-[11.5px] font-bold tracking-wide text-os-sub"
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
                                          borderColor:
                                              MEMORY_TYPES[key].color + '55',
                                      }
                                    : undefined
                            "
                            @click="toggleType(key)"
                        >
                            <component
                                :is="MEMORY_TYPES[key].icon"
                                :size="12"
                            />
                            {{ MEMORY_TYPES[key].label }}
                            {{ typeCounts[key] || 0 }}
                        </button>
                    </div>
                </section>

                <section
                    v-if="tagCandidates.length > 0 || selectedTags.length > 0"
                >
                    <div
                        class="mb-2 text-[11.5px] font-bold tracking-wide text-os-sub"
                    >
                        タグでしぼる
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="item in tagCandidates"
                            :key="item.tag"
                            type="button"
                            class="inline-flex max-w-full items-center gap-1 rounded-full border px-3 py-1.5 text-xs break-all transition-colors"
                            :class="
                                selectedTags.includes(item.tag)
                                    ? 'border-os-kioku/40 bg-os-kioku-soft font-bold text-os-kioku'
                                    : 'border-os-line bg-[#F0ECE0] text-os-sub'
                            "
                            @click="toggleTag(item.tag)"
                        >
                            #{{ item.tag }}
                            <span class="font-mono text-[10.5px] opacity-70">{{
                                item.count
                            }}</span>
                        </button>
                        <button
                            v-for="tag in selectedTags.filter(
                                (selected) =>
                                    !tagCandidates.some(
                                        (item) => item.tag === selected,
                                    ),
                            )"
                            :key="`selected-${tag}`"
                            type="button"
                            class="inline-flex max-w-full items-center gap-1 rounded-full border border-os-kioku/40 bg-os-kioku-soft px-3 py-1.5 text-xs font-bold break-all text-os-kioku"
                            @click="toggleTag(tag)"
                        >
                            #{{ tag }}
                            <X :size="11" />
                        </button>
                    </div>
                    <div
                        v-if="selectedTags.length >= 2"
                        class="mt-3 flex flex-wrap items-center gap-2"
                        role="group"
                        aria-label="タグの組み合わせ"
                    >
                        <span class="text-[11px] text-os-sub">組み合わせ</span>
                        <button
                            type="button"
                            class="rounded-full border px-3 py-1 text-[11.5px] transition-colors"
                            :class="
                                tagMode === 'and'
                                    ? 'border-os-kioku/40 bg-os-kioku-soft font-bold text-os-kioku'
                                    : 'border-os-line bg-[#F0ECE0] text-os-sub'
                            "
                            @click="setTagMode('and')"
                        >
                            AND（すべて含む）
                        </button>
                        <button
                            type="button"
                            class="rounded-full border px-3 py-1 text-[11.5px] transition-colors"
                            :class="
                                tagMode === 'or'
                                    ? 'border-os-kioku/40 bg-os-kioku-soft font-bold text-os-kioku'
                                    : 'border-os-line bg-[#F0ECE0] text-os-sub'
                            "
                            @click="setTagMode('or')"
                        >
                            OR（いずれかを含む）
                        </button>
                    </div>
                </section>
            </div>
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-2"
            role="group"
            aria-label="表示切替"
        >
            <div class="flex flex-wrap gap-1.5">
                <button
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs transition-colors"
                    :class="
                        viewMode === 'timeline'
                            ? 'border-os-kioku/40 bg-os-kioku-soft font-bold text-os-kioku'
                            : 'border-os-line bg-[#F0ECE0] text-os-sub'
                    "
                    @click="viewMode = 'timeline'"
                >
                    時系列
                </button>
                <button
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs transition-colors"
                    :class="
                        viewMode === 'tags'
                            ? 'border-os-kioku/40 bg-os-kioku-soft font-bold text-os-kioku'
                            : 'border-os-line bg-[#F0ECE0] text-os-sub'
                    "
                    @click="viewMode = 'tags'"
                >
                    タグ
                </button>
            </div>
            <p class="text-[11.5px] text-os-sub">
                {{ memories.length }}件
                <span v-if="hasActiveFilters">（絞り込み中）</span>
            </p>
        </div>

        <div
            v-if="memories.length === 0"
            class="rounded-2xl border border-os-line bg-os-kioku-paper p-9 text-center shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
        >
            <p class="text-[13px] leading-relaxed text-os-sub">
                {{
                    hasActiveFilters
                        ? '条件に一致する記憶はありません。'
                        : 'まだ記憶がありません。ホームの入力欄から残せます。'
                }}
            </p>
        </div>

        <template v-else-if="viewMode === 'timeline'">
            <MemoryCard
                v-for="memory in memories"
                :key="memory.id"
                :memory="memory"
                :transcription-enabled="transcriptionEnabled"
            />
        </template>

        <template v-else>
            <div
                v-if="tagGroups.length === 0"
                class="rounded-2xl border border-os-line bg-os-kioku-paper p-9 text-center"
            >
                <p class="text-[13px] leading-relaxed text-os-sub">
                    表示できるタググループがありません。
                </p>
            </div>
            <section
                v-for="group in tagGroups"
                :key="group.untagged ? '__untagged__' : group.tag"
                class="space-y-2.5"
            >
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2 px-0.5"
                >
                    <h3
                        class="text-[12.5px] font-bold tracking-wide break-all"
                        :class="
                            group.untagged ? 'text-os-sub' : 'text-os-kioku'
                        "
                    >
                        {{ group.untagged ? group.tag : `#${group.tag}` }}
                    </h3>
                    <span class="font-mono text-[11px] text-os-sub"
                        >{{ group.memories.length }}件</span
                    >
                </div>
                <MemoryCard
                    v-for="memory in group.memories"
                    :key="`${group.untagged ? 'untagged' : group.tag}-${memory.id}`"
                    :memory="memory"
                    :transcription-enabled="transcriptionEnabled"
                />
            </section>
        </template>

        <p class="pt-1 text-center text-[11px] leading-relaxed text-os-sub">
            タグや内容から、関連するキオクを見つけます。
        </p>
    </div>
</template>
