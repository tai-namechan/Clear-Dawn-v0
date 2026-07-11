<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ChevronRight, Clock, Compass, RefreshCw, Sparkles, Sun } from '@lucide/vue';
import { toast } from 'vue-sonner';
import SourceBadge from '@/components/kioku/SourceBadge.vue';
import TypeChip from '@/components/kioku/TypeChip.vue';
import { Button } from '@/components/ui/button';
import { formatAgo } from '@/lib/kiokuMeta';
import { home } from '@/routes/kioku';
import { reenrich, show } from '@/routes/kioku/memories';
import type { KiokuMemory } from '@/types/kioku';

interface Props {
    memory: KiokuMemory;
    related: KiokuMemory[];
}

const props = defineProps<Props>();

function requestReenrich(): void {
    router.post(reenrich.url(props.memory.id), {}, { preserveScroll: true });
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
    <div class="mx-auto max-w-[640px] space-y-4">
        <Head :title="memory.title" />

        <Link
            :href="home()"
            class="inline-flex items-center gap-1 text-sm text-os-sub hover:text-os-ink"
        >
            <ArrowLeft :size="14" />
            一覧へ
        </Link>

        <article
            class="overflow-hidden rounded-[20px] border border-os-line bg-os-kioku-paper shadow-[0_8px_28px_rgba(43,41,36,0.1)]"
        >
            <div class="space-y-3 px-5 pt-5">
                <div class="flex flex-wrap items-center gap-2.5">
                    <TypeChip
                        v-if="memory.memory_type"
                        :type="memory.memory_type"
                    />
                    <SourceBadge :source="memory.source_type" />
                    <span
                        class="inline-flex items-center gap-1 text-[11px] text-os-faint"
                    >
                        <Clock :size="11" />
                        {{ formatAgo(memory.captured_at) }}
                    </span>
                </div>

                <h1 class="text-lg font-bold text-os-ink">{{ memory.title }}</h1>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[11px] tracking-wide text-[#B8862B]">
                        <span>{{ '★'.repeat(memory.importance) }}</span>
                        <span class="text-os-line">{{
                            '★'.repeat(5 - memory.importance)
                        }}</span>
                    </span>
                    <span
                        v-for="tag in memory.tags"
                        :key="tag"
                        class="text-[11.5px] text-os-kioku"
                    >
                        #{{ tag }}
                    </span>
                </div>
            </div>

            <div class="space-y-4 px-5 py-4">
                <div
                    v-if="memory.summary"
                    class="rounded-xl bg-os-kioku-soft px-3.5 py-3 text-[13.5px] leading-relaxed text-os-ink"
                >
                    <span
                        class="mb-1 block text-[11px] font-bold text-os-kioku"
                        >AI要約</span
                    >
                    {{ memory.summary }}
                </div>

                <div
                    v-if="memory.display_fields.length && memory.structured_data"
                    class="space-y-3"
                >
                    <div
                        v-for="field in memory.display_fields"
                        :key="field.key"
                    >
                        <div
                            class="mb-1.5 text-[11px] font-bold tracking-wide text-os-faint"
                        >
                            {{ field.label }}
                        </div>
                        <template v-if="field.type === 'list'">
                            <div class="space-y-1">
                                <div
                                    v-for="(item, idx) in (fieldValue(
                                        memory.structured_data,
                                        field.key,
                                    ) as unknown[]) || []"
                                    :key="idx"
                                    class="flex gap-2 py-0.5"
                                >
                                    <span
                                        class="text-xs font-bold text-os-kioku"
                                        >{{ Number(idx) + 1 }}.</span
                                    >
                                    <code
                                        class="rounded-md bg-os-kioku-soft px-2 py-0.5 font-mono text-[12.5px]"
                                        >{{ item }}</code
                                    >
                                </div>
                            </div>
                        </template>
                        <template v-else-if="field.type === 'boolean'">
                            <span
                                class="inline-flex rounded-full px-3 py-1 text-[11.5px] font-bold"
                                :class="
                                    fieldValue(memory.structured_data, field.key)
                                        ? 'bg-[#E8F0E5] text-[#5D8A5F]'
                                        : 'bg-[#F8E9E4] text-[#C05A48]'
                                "
                            >
                                {{
                                    fieldValue(memory.structured_data, field.key)
                                        ? '解決済み'
                                        : '未解決'
                                }}
                            </span>
                        </template>
                        <template v-else-if="field.key === 'error_message'">
                            <code
                                class="block overflow-x-auto rounded-[10px] bg-[#2B2924] px-3.5 py-2.5 font-mono text-xs text-[#F0B4A2]"
                                >{{
                                    fieldValue(memory.structured_data, field.key)
                                }}</code
                            >
                        </template>
                        <template v-else>
                            <p
                                class="text-[13px] leading-relaxed whitespace-pre-wrap text-os-ink"
                            >
                                {{
                                    fieldValue(memory.structured_data, field.key) ??
                                    '—'
                                }}
                            </p>
                        </template>
                    </div>
                </div>

                <div>
                    <div
                        class="mb-1.5 text-[11px] font-bold tracking-wide text-os-faint"
                    >
                        原文
                    </div>
                    <div
                        class="text-[12.5px] leading-relaxed break-all whitespace-pre-wrap text-os-sub"
                    >
                        {{ memory.raw_content }}
                    </div>
                </div>

                <div v-if="related.length" class="pt-1">
                    <div
                        class="mb-2 flex items-center gap-1.5 text-[11px] font-bold tracking-wide text-os-kioku"
                    >
                        <Sparkles :size="12" />
                        関連する記憶
                    </div>
                    <Link
                        v-for="item in related"
                        :key="item.id"
                        :href="show.url(item.id)"
                        class="mb-1.5 flex items-center gap-2 rounded-[11px] bg-os-kioku-bg px-3 py-2.5"
                    >
                        <TypeChip
                            v-if="item.memory_type"
                            :type="item.memory_type"
                            small
                        />
                        <span class="flex-1 text-[12.5px] text-os-ink">{{
                            item.title
                        }}</span>
                        <ChevronRight :size="14" class="text-os-faint" />
                    </Link>
                </div>
            </div>

            <div
                class="flex flex-wrap gap-2 border-t border-os-line px-5 py-4"
            >
                <Button
                    type="button"
                    class="gap-1.5 rounded-full border border-[#12948844] bg-[#E4F4F2] text-[#129488] hover:bg-[#E4F4F2]"
                    variant="outline"
                    @click="toast.message('ヨユウのタスクに送信しました（モック）')"
                >
                    <Sun :size="13" />
                    ヨユウのタスクへ
                </Button>
                <Button
                    type="button"
                    class="gap-1.5 rounded-full border border-[#5C4E8E44] bg-[#EDEAF5] text-[#5C4E8E] hover:bg-[#EDEAF5]"
                    variant="outline"
                    @click="
                        toast.message('Clear Dawnの目標に紐づけました（モック）')
                    "
                >
                    <Compass :size="13" />
                    Clear Dawnへ
                </Button>
                <Button
                    v-if="
                        memory.status === 'ready' || memory.status === 'failed'
                    "
                    type="button"
                    class="gap-1.5 rounded-full border border-os-line text-os-sub hover:bg-os-kioku-soft"
                    variant="outline"
                    @click="requestReenrich"
                >
                    <RefreshCw :size="13" />
                    AIで再整理
                </Button>
            </div>
        </article>
    </div>
</template>
