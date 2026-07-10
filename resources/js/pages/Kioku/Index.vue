<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Database, RefreshCw, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { home, settings, sources } from '@/routes/kioku';
import { show, store } from '@/routes/kioku/memories';
import type { KiokuMemory, MemoryTypeOption } from '@/types/kioku';

interface Props {
    memories: KiokuMemory[];
    filters: { q: string | null; types: string[] };
    memoryTypes: MemoryTypeOption[];
}

const props = defineProps<Props>();

const q = ref(props.filters.q ?? '');
const selectedTypes = ref<string[]>([...props.filters.types]);

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

function reload(): void {
    router.reload({ only: ['memories'] });
}

defineOptions({
    layout: {
        title: 'キオク',
        subtitle: '記憶の保存・検索・想起',
    },
});
</script>

<template>
    <div class="space-y-6">
        <Head title="キオク" />

        <nav class="flex flex-wrap gap-2 text-sm">
            <Link
                :href="home()"
                class="rounded-full bg-os-kioku px-3 py-1.5 font-medium text-white"
            >
                記憶
            </Link>
            <Link
                :href="sources()"
                class="rounded-full border border-border px-3 py-1.5 text-muted-foreground hover:bg-muted"
            >
                取り込み元
            </Link>
            <Link
                :href="settings()"
                class="rounded-full border border-border px-3 py-1.5 text-muted-foreground hover:bg-muted"
            >
                設定
            </Link>
        </nav>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
            <aside class="space-y-4">
                <section
                    class="rounded-xl border border-border bg-card p-4 shadow-sm"
                >
                    <div
                        class="mb-3 flex items-center gap-2 text-sm font-semibold text-os-kioku"
                    >
                        <Database :size="16" />
                        今すぐ保存
                    </div>
                    <Form
                        v-bind="store.form()"
                        class="space-y-3"
                        #default="{ processing }"
                    >
                        <textarea
                            name="raw_content"
                            rows="5"
                            required
                            placeholder="テキストやURLを貼り付け…"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm leading-relaxed outline-none focus-visible:ring-2 focus-visible:ring-os-kioku"
                        />
                        <input type="hidden" name="source_type" value="manual" />
                        <Button
                            type="submit"
                            class="w-full bg-os-kioku text-white hover:bg-os-kioku/90"
                            :disabled="processing"
                        >
                            保存する
                        </Button>
                    </Form>
                </section>

                <section
                    class="rounded-xl border border-border bg-card p-4 shadow-sm"
                >
                    <div class="mb-3 text-sm font-semibold text-muted-foreground">
                        フィルター
                    </div>
                    <div class="mb-3 flex gap-2">
                        <Input
                            v-model="q"
                            placeholder="キーワード"
                            class="text-sm"
                            @keydown.enter.prevent="applyFilters"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            @click="applyFilters"
                        >
                            <Search :size="16" />
                        </Button>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="type in memoryTypes"
                            :key="type.key"
                            type="button"
                            class="rounded-full border px-2.5 py-1 text-xs"
                            :class="
                                selectedTypes.includes(type.key)
                                    ? 'border-os-kioku bg-os-kioku/10 text-os-kioku'
                                    : 'border-border text-muted-foreground'
                            "
                            @click="toggleType(type.key)"
                        >
                            {{ type.label }}
                        </button>
                    </div>
                </section>
            </aside>

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-muted-foreground">
                        記憶一覧 — {{ memories.length }}件
                    </h2>
                    <Button
                        v-if="hasEnriching"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="gap-1.5"
                        @click="reload"
                    >
                        <RefreshCw :size="14" />
                        更新
                    </Button>
                </div>

                <div
                    v-if="memories.length === 0"
                    class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
                >
                    まだ記憶がありません。左のボックスから保存してみてください。
                </div>

                <Link
                    v-for="memory in memories"
                    :key="memory.id"
                    :href="show(memory.id)"
                    class="block rounded-xl border border-border bg-card p-4 shadow-sm transition-shadow hover:shadow-md"
                >
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium"
                        >
                            {{ memory.memory_type_label ?? '未分類' }}
                        </span>
                        <span
                            v-if="
                                memory.status === 'enriching' ||
                                memory.status === 'captured'
                            "
                            class="rounded-full bg-os-kioku/10 px-2 py-0.5 text-[11px] font-medium text-os-kioku"
                        >
                            AIが整理中…
                        </span>
                        <span
                            v-else-if="memory.status === 'failed'"
                            class="rounded-full bg-destructive/10 px-2 py-0.5 text-[11px] font-medium text-destructive"
                        >
                            整理失敗（原文は保存済み）
                        </span>
                        <span class="text-[11px] text-muted-foreground">
                            {{ memory.source_type }}
                        </span>
                    </div>
                    <div class="font-medium text-foreground">
                        {{ memory.title }}
                    </div>
                    <p
                        v-if="memory.summary"
                        class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ memory.summary }}
                    </p>
                    <div
                        v-if="memory.tags?.length"
                        class="mt-2 flex flex-wrap gap-1"
                    >
                        <span
                            v-for="tag in memory.tags"
                            :key="tag"
                            class="rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground"
                        >
                            #{{ tag }}
                        </span>
                    </div>
                </Link>
            </section>
        </div>
    </div>
</template>
