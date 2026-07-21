<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronRight,
    Clock,
    Plus,
    RefreshCw,
    Sparkles,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SourceBadge from '@/components/kioku/SourceBadge.vue';
import TypeChip from '@/components/kioku/TypeChip.vue';
import { Button } from '@/components/ui/button';
import {
    canKiokuMemoryReenrich,
    kiokuMemoryDisplayTitle,
} from '@/lib/kiokuMemoryCard.mjs';
import { formatAgo, sourceTypeMeta } from '@/lib/kiokuMeta';
import { relatedMemoryReason } from '@/lib/kiokuRelated.mjs';
import { KIOKU_MAX_TAG_CHARS, KIOKU_MAX_TAGS } from '@/lib/kiokuTags.mjs';
import { kiokuTranscriptDisplayMode } from '@/lib/kiokuTranscriptDisplay.mjs';
import { home } from '@/routes/kioku';
import {
    audio,
    index as memoriesIndex,
    reenrich,
    retryTranscription,
    show,
} from '@/routes/kioku/memories';
import { update as updateTags } from '@/routes/kioku/memories/tags';
import type { KiokuMemory, UpdateMemoryTagsPayload } from '@/types/kioku';

interface Props {
    memory: KiokuMemory;
    related: KiokuMemory[];
    transcriptionEnabled: boolean;
}

const props = defineProps<Props>();
const page = usePage();

const audioMissing = ref(false);
const draftTags = ref<string[]>([...props.memory.tags]);
const tagDraft = ref('');
const tagClientError = ref<string | null>(null);
const tagSaving = ref(false);
const tagSaveSucceeded = ref(false);

watch(
    () => props.memory.id,
    () => {
        audioMissing.value = false;
        resetTagDraft();
    },
);

watch(
    () => props.memory.tags,
    (tags) => {
        if (!tagSaving.value) {
            draftTags.value = [...tags];
            tagSaveSucceeded.value = false;
            tagClientError.value = null;
        }
    },
);

const transcriptMode = computed(() =>
    kiokuTranscriptDisplayMode({
        transcriptionEnabled: props.transcriptionEnabled,
        transcriptionStatus: props.memory.transcription_status,
        transcriptText: props.memory.transcript_text,
    }),
);

const displayTitle = computed(() => kiokuMemoryDisplayTitle(props.memory));

const canReenrich = computed(() => canKiokuMemoryReenrich(props.memory));

const titleClass = computed(
    () => sourceTypeMeta(props.memory.source_type).titleClass ?? 'text-os-ink',
);

const tagsDirty = computed(() => {
    const current = props.memory.tags;

    if (current.length !== draftTags.value.length) {
        return true;
    }

    return draftTags.value.some((tag, index) => tag !== current[index]);
});

const tagServerErrors = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined;

    if (!errors) {
        return [] as string[];
    }

    return Object.entries(errors)
        .filter(([key]) => key === 'tags' || key.startsWith('tags.'))
        .map(([, message]) => message);
});

function onAudioError(): void {
    audioMissing.value = true;
}

function requestReenrich(): void {
    router.post(reenrich.url(props.memory.id), {}, { preserveScroll: true });
}

function requestRetryTranscription(): void {
    router.post(
        retryTranscription.url(props.memory.id),
        {},
        { preserveScroll: true },
    );
}

function cleanupTagInput(value: string): string {
    return value
        .replace(/^[\s\u3000]+|[\s\u3000]+$/gu, '')
        .replace(/[\s\u3000]+/gu, ' ')
        .replace(/^[#＃]+/u, '')
        .replace(/^[\s\u3000]+|[\s\u3000]+$/gu, '');
}

function addTagFromDraft(): void {
    tagClientError.value = null;
    tagSaveSucceeded.value = false;

    const next = cleanupTagInput(tagDraft.value);

    if (next === '') {
        tagDraft.value = '';

        return;
    }

    if ([...next].length > KIOKU_MAX_TAG_CHARS) {
        tagClientError.value = `タグは${KIOKU_MAX_TAG_CHARS}文字以内で入力してください。`;

        return;
    }

    if (draftTags.value.length >= KIOKU_MAX_TAGS) {
        tagClientError.value = `タグは最大${KIOKU_MAX_TAGS}件までです。`;

        return;
    }

    const exists = draftTags.value.some(
        (tag) => tag.toLocaleLowerCase() === next.toLocaleLowerCase(),
    );

    if (exists) {
        tagDraft.value = '';

        return;
    }

    draftTags.value = [...draftTags.value, next];
    tagDraft.value = '';
}

function removeDraftTag(tag: string): void {
    tagClientError.value = null;
    tagSaveSucceeded.value = false;
    draftTags.value = draftTags.value.filter((current) => current !== tag);
}

function resetTagDraft(): void {
    draftTags.value = [...props.memory.tags];
    tagDraft.value = '';
    tagClientError.value = null;
    tagSaveSucceeded.value = false;
}

function saveTags(): void {
    if (tagSaving.value) {
        return;
    }

    tagClientError.value = null;
    tagSaveSucceeded.value = false;
    tagSaving.value = true;

    const payload: UpdateMemoryTagsPayload = { tags: draftTags.value };

    router.put(updateTags.url(props.memory.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            tagSaveSucceeded.value = true;
            tagDraft.value = '';
        },
        onFinish: () => {
            tagSaving.value = false;
        },
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
    <div class="mx-auto max-w-[640px] space-y-4">
        <Head :title="displayTitle" />

        <div class="flex flex-wrap gap-3 text-sm">
            <Link
                :href="home()"
                class="inline-flex items-center gap-1 text-os-sub hover:text-os-ink"
            >
                <ArrowLeft :size="14" />
                ホームへ
            </Link>
            <Link
                :href="memoriesIndex()"
                class="inline-flex items-center gap-1 text-os-sub hover:text-os-ink"
            >
                キオクを探す
            </Link>
        </div>

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
                        class="inline-flex items-center gap-1 text-[11px] text-os-sub"
                    >
                        <Clock :size="11" />
                        {{ formatAgo(memory.captured_at) }}
                    </span>
                </div>

                <h1 class="text-lg font-bold" :class="titleClass">
                    {{ displayTitle }}
                </h1>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[11px] tracking-wide text-[#B8862B]">
                        <span>{{ '★'.repeat(memory.importance) }}</span>
                        <span class="text-os-line">{{
                            '★'.repeat(5 - memory.importance)
                        }}</span>
                    </span>
                </div>
            </div>

            <div class="space-y-4 px-5 py-4">
                <section
                    class="rounded-xl border border-os-line bg-os-kioku-bg px-3.5 py-3"
                    aria-label="タグ編集"
                >
                    <div
                        class="mb-2 flex flex-wrap items-center justify-between gap-2"
                    >
                        <div
                            class="text-[11px] font-bold tracking-wide text-os-sub"
                        >
                            タグ（解釈層・原文/音声は変わりません）
                        </div>
                        <span class="font-mono text-[11px] text-os-sub"
                            >{{ draftTags.length }}/{{ KIOKU_MAX_TAGS }}</span
                        >
                    </div>

                    <div class="mb-2.5 flex flex-wrap gap-1.5">
                        <span
                            v-if="draftTags.length === 0"
                            class="text-[12px] text-os-sub"
                            >タグなし（未分類として表示されます）</span
                        >
                        <span
                            v-for="tag in draftTags"
                            :key="tag"
                            class="inline-flex items-center gap-1 rounded-full border border-os-kioku/30 bg-os-kioku-soft py-1 pr-1 pl-2.5 text-[11.5px] font-medium text-os-kioku"
                        >
                            #{{ tag }}
                            <button
                                type="button"
                                class="inline-flex h-5 w-5 items-center justify-center rounded-full text-os-kioku transition-colors hover:bg-os-kioku/10 disabled:opacity-50"
                                :aria-label="`${tag} を削除`"
                                :disabled="tagSaving"
                                @click="removeDraftTag(tag)"
                            >
                                <X :size="11" />
                            </button>
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <input
                            v-model="tagDraft"
                            type="text"
                            maxlength="80"
                            placeholder="タグを追加（Enter）"
                            class="min-w-0 flex-1 rounded-xl border border-os-line bg-os-kioku-paper px-3 py-2 text-[13px] text-os-ink outline-none placeholder:text-[#B3AC99] focus-visible:ring-2 focus-visible:ring-os-kioku/35"
                            :disabled="tagSaving"
                            @keydown.enter.prevent="addTagFromDraft"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 gap-1 rounded-xl border-os-line text-xs text-os-sub"
                            :disabled="tagSaving || !tagDraft.trim()"
                            @click="addTagFromDraft"
                        >
                            <Plus :size="12" />
                            追加
                        </Button>
                    </div>

                    <p
                        v-if="tagClientError"
                        class="mt-2 text-[12px] text-[#C05A48]"
                        role="alert"
                    >
                        {{ tagClientError }}
                    </p>
                    <p
                        v-for="(message, index) in tagServerErrors"
                        :key="`tag-error-${index}`"
                        class="mt-2 text-[12px] text-[#C05A48]"
                        role="alert"
                    >
                        {{ message }}
                    </p>
                    <p
                        v-if="tagSaveSucceeded && !tagsDirty"
                        class="mt-2 text-[12px] text-os-kioku"
                        role="status"
                    >
                        タグを更新しました。
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button
                            type="button"
                            class="h-9 rounded-xl bg-os-kioku text-xs font-bold text-white hover:bg-os-kioku/90"
                            :disabled="tagSaving || !tagsDirty"
                            @click="saveTags"
                        >
                            {{ tagSaving ? '保存中…' : 'タグを保存' }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-9 rounded-xl border-os-line text-xs text-os-sub"
                            :disabled="tagSaving || (!tagsDirty && !tagDraft)"
                            @click="resetTagDraft"
                        >
                            キャンセル
                        </Button>
                    </div>
                </section>

                <div
                    v-if="memory.summary"
                    class="rounded-xl bg-os-kioku-soft px-3.5 py-3 text-[13.5px] leading-relaxed text-os-ink"
                >
                    <span class="mb-1 block text-[11px] font-bold text-os-kioku"
                        >AI要約</span
                    >
                    {{ memory.summary }}
                </div>

                <div
                    v-if="
                        memory.display_fields.length && memory.structured_data
                    "
                    class="space-y-3"
                >
                    <div
                        v-for="field in memory.display_fields"
                        :key="field.key"
                    >
                        <div
                            class="mb-1.5 text-[11px] font-bold tracking-wide text-os-sub"
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
                                    fieldValue(
                                        memory.structured_data,
                                        field.key,
                                    )
                                        ? 'bg-[#E8F0E5] text-[#5D8A5F]'
                                        : 'bg-[#F8E9E4] text-[#C05A48]'
                                "
                            >
                                {{
                                    fieldValue(
                                        memory.structured_data,
                                        field.key,
                                    )
                                        ? '解決済み'
                                        : '未解決'
                                }}
                            </span>
                        </template>
                        <template v-else-if="field.key === 'error_message'">
                            <code
                                class="block overflow-x-auto rounded-[10px] bg-[#2B2924] px-3.5 py-2.5 font-mono text-xs text-[#F0B4A2]"
                                >{{
                                    fieldValue(
                                        memory.structured_data,
                                        field.key,
                                    )
                                }}</code
                            >
                        </template>
                        <template v-else>
                            <p
                                class="text-[13px] leading-relaxed whitespace-pre-wrap text-os-ink"
                            >
                                {{
                                    fieldValue(
                                        memory.structured_data,
                                        field.key,
                                    ) ?? '—'
                                }}
                            </p>
                        </template>
                    </div>
                </div>

                <div v-if="memory.source_type === 'voice'">
                    <div
                        class="mb-1.5 text-[11px] font-bold tracking-wide text-os-sub"
                    >
                        原音声（この記憶の原本）
                    </div>
                    <div v-if="audioMissing" class="space-y-1">
                        <p class="text-[12.5px] leading-relaxed text-[#C05A48]">
                            原音声ファイルが見つかりません。
                        </p>
                        <p class="text-[12.5px] leading-relaxed text-os-sub">
                            この記録の音声は復旧できないため、必要であれば再録音してください。
                        </p>
                    </div>
                    <audio
                        v-else
                        controls
                        preload="metadata"
                        class="w-full"
                        :src="audio.url(memory.id)"
                        @error="onAudioError"
                    ></audio>
                </div>

                <div v-if="memory.source_type === 'voice'">
                    <div
                        class="mb-1.5 text-[11px] font-bold tracking-wide text-os-sub"
                    >
                        文字起こし（自動生成・原音声は変更されません）
                    </div>
                    <p
                        v-if="transcriptMode === 'text'"
                        class="text-[12.5px] leading-relaxed break-all whitespace-pre-wrap text-os-sub"
                    >
                        {{ memory.transcript_text }}
                    </p>
                    <p
                        v-else-if="transcriptMode === 'empty_ready'"
                        class="text-[12.5px] leading-relaxed text-os-sub"
                    >
                        音声を文字として認識できませんでした。原音声は残っています。
                    </p>
                    <p
                        v-else-if="transcriptMode === 'not_configured'"
                        class="text-[12.5px] leading-relaxed text-os-sub"
                    >
                        文字起こしは未設定です。原音声はサーバーに保存されています。
                    </p>
                    <div v-else-if="transcriptMode === 'failed'">
                        <p
                            class="mb-2 text-[12.5px] leading-relaxed text-[#C05A48]"
                        >
                            文字起こしに失敗しました。原音声は残っています。
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            class="gap-1.5 rounded-full border-os-line text-xs text-os-sub hover:bg-os-kioku-soft"
                            @click="requestRetryTranscription"
                        >
                            <RefreshCw :size="12" />
                            文字起こしを再実行
                        </Button>
                    </div>
                    <p v-else class="text-[12.5px] leading-relaxed text-os-sub">
                        文字起こし中です…
                    </p>
                </div>

                <div v-if="memory.raw_content !== null">
                    <div
                        class="mb-1.5 text-[11px] font-bold tracking-wide text-os-sub"
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
                        このキオクとつながる記憶
                    </div>
                    <p class="mb-2 text-[11.5px] leading-relaxed text-os-sub">
                        タグや内容から、関連するキオクを見つけます。
                    </p>
                    <Link
                        v-for="item in related"
                        :key="item.id"
                        :href="show.url(item.id)"
                        class="mb-1.5 flex items-start gap-2 rounded-[11px] bg-os-kioku-bg px-3 py-2.5"
                    >
                        <TypeChip
                            v-if="item.memory_type"
                            :type="item.memory_type"
                            small
                        />
                        <span class="min-w-0 flex-1">
                            <span
                                class="block text-[12.5px] font-medium"
                                :class="
                                    sourceTypeMeta(item.source_type)
                                        .titleClass ?? 'text-os-ink'
                                "
                                >{{ kiokuMemoryDisplayTitle(item) }}</span
                            >
                            <span
                                class="mt-0.5 block text-[11px] leading-relaxed text-os-sub"
                            >
                                {{ relatedMemoryReason(memory, item) }}
                            </span>
                        </span>
                        <ChevronRight
                            :size="14"
                            class="mt-0.5 shrink-0 text-os-faint"
                        />
                    </Link>
                </div>
            </div>

            <div
                v-if="canReenrich"
                class="flex flex-wrap gap-2 border-t border-os-line px-5 py-4"
            >
                <Button
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
