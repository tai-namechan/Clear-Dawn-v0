<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes/kioku';
import { show } from '@/routes/kioku/memories';
import type { KiokuMemory } from '@/types/kioku';

interface Props {
    memory: KiokuMemory;
    related: KiokuMemory[];
}

defineProps<Props>();

function toastUnimplemented(label: string): void {
    // vue-sonner is initialized globally; dynamic import avoided for simplicity
    import('vue-sonner').then(({ toast }) => {
        toast.message(`${label}は未実装です`);
    });
}

function fieldValue(
    data: Record<string, unknown> | null,
    key: string,
): unknown {
    return data?.[key] ?? null;
}

defineOptions({
    layout: {
        title: 'キオク',
        subtitle: '記憶の詳細',
    },
});
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <Head :title="memory.title" />

        <Link
            :href="home()"
            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft :size="14" />
            一覧へ
        </Link>

        <article class="rounded-xl border border-border bg-card p-6 shadow-sm">
            <div class="mb-3 flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-muted px-2 py-0.5">
                    {{ memory.memory_type_label ?? '未分類' }}
                </span>
                <span class="rounded-full bg-muted px-2 py-0.5">
                    {{ memory.source_type }}
                </span>
                <span class="rounded-full bg-muted px-2 py-0.5">
                    重要度 {{ memory.importance }}
                </span>
                <span
                    v-if="memory.status === 'failed'"
                    class="rounded-full bg-destructive/10 px-2 py-0.5 text-destructive"
                >
                    整理失敗
                </span>
            </div>

            <h1 class="text-xl font-semibold">{{ memory.title }}</h1>
            <p
                v-if="memory.summary"
                class="mt-2 text-sm leading-relaxed text-muted-foreground"
            >
                {{ memory.summary }}
            </p>

            <div
                v-if="memory.display_fields.length && memory.structured_data"
                class="mt-6 space-y-3 border-t border-border pt-4"
            >
                <h2 class="text-sm font-semibold text-os-kioku">構造化データ</h2>
                <div
                    v-for="field in memory.display_fields"
                    :key="field.key"
                    class="text-sm"
                >
                    <div class="mb-1 font-medium text-muted-foreground">
                        {{ field.label }}
                    </div>
                    <template v-if="field.type === 'list'">
                        <ol class="list-decimal space-y-1 pl-5">
                            <li
                                v-for="(item, idx) in (fieldValue(
                                    memory.structured_data,
                                    field.key,
                                ) as unknown[]) || []"
                                :key="idx"
                            >
                                {{ item }}
                            </li>
                        </ol>
                    </template>
                    <template v-else-if="field.type === 'boolean'">
                        <span>
                            {{
                                fieldValue(memory.structured_data, field.key)
                                    ? 'はい'
                                    : 'いいえ'
                            }}
                        </span>
                    </template>
                    <template v-else>
                        <p class="leading-relaxed whitespace-pre-wrap">
                            {{
                                fieldValue(memory.structured_data, field.key) ??
                                '—'
                            }}
                        </p>
                    </template>
                </div>
            </div>

            <div class="mt-6 border-t border-border pt-4">
                <h2 class="mb-2 text-sm font-semibold text-muted-foreground">
                    原文
                </h2>
                <pre
                    class="overflow-x-auto rounded-lg bg-muted/50 p-3 text-sm leading-relaxed whitespace-pre-wrap"
                    >{{ memory.raw_content }}</pre
                >
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="toastUnimplemented('ヨユウのタスクへ')"
                >
                    ヨユウのタスクへ
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    @click="toastUnimplemented('Clear Dawnに紐づける')"
                >
                    Clear Dawnに紐づける
                </Button>
            </div>
        </article>

        <section
            v-if="related.length"
            class="rounded-xl border border-border bg-card p-5 shadow-sm"
        >
            <h2 class="mb-3 text-sm font-semibold text-os-kioku">
                関連する記憶
            </h2>
            <div class="space-y-2">
                <Link
                    v-for="item in related"
                    :key="item.id"
                    :href="show.url(item.id)"
                    class="block rounded-lg border border-border px-3 py-2 text-sm hover:bg-muted/40"
                >
                    <div class="font-medium">{{ item.title }}</div>
                    <div class="text-xs text-muted-foreground">
                        {{ item.memory_type_label }} ·
                        {{ item.summary || item.raw_content.slice(0, 80) }}
                    </div>
                </Link>
            </div>
        </section>
    </div>
</template>
