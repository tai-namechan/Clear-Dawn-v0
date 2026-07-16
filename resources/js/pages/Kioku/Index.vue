<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Brain, CloudOff, Mic, Plus, Search, Send, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import KiokuLetterPreview from '@/components/kioku/KiokuLetterPreview.vue';
import MemoryCard from '@/components/kioku/MemoryCard.vue';
import VoiceCaptureOverlay from '@/components/kioku/VoiceCaptureOverlay.vue';
import { Button } from '@/components/ui/button';
import { useAudioRecorder } from '@/composables/useAudioRecorder';
import type { RecordedAudio } from '@/composables/useAudioRecorder';
import { useKiokuCaptureQueue } from '@/composables/useKiokuCaptureQueue';
import { useKiokuStatusPoll } from '@/composables/useKiokuStatusPoll';
import { KIOKU_MAX_RECORDING_MS } from '@/lib/kiokuAudioRecorder.mjs';
import { buildCaptureQueueItem } from '@/lib/kiokuCaptureQueue.mjs';
import { MEMORY_TYPES, SOURCE_TYPES } from '@/lib/kiokuMeta';
import type { MemoryTypeKey, SourceTypeKey } from '@/lib/kiokuMeta';
import {
    buildKiokuHomeQuery,
    groupMemoriesByTag,
    normalizeTagMode,
    toggleTagFilter,
    visibleTagCounts,
} from '@/lib/kiokuTags.mjs';
import { home } from '@/routes/kioku';
import type {
    KiokuHomeFilters,
    KiokuMemory,
    KiokuTagMode,
    MemoryTypeOption,
} from '@/types/kioku';
import type { KiokuLetterSummary } from '@/types/kiokuLetter';

type HomeViewMode = 'timeline' | 'tags';

interface Props {
    memories: KiokuMemory[];
    filters: KiokuHomeFilters;
    memoryTypes: MemoryTypeOption[];
    typeCounts: Record<string, number>;
    sourceCounts: Record<string, number>;
    totalCount: number;
    transcriptionEnabled: boolean;
    letters: KiokuLetterSummary[];
    testLetters?: KiokuLetterSummary[];
}

const props = defineProps<Props>();

const q = ref(props.filters.q ?? '');
const selectedTypes = ref<string[]>([...props.filters.types]);
const selectedTags = ref<string[]>([...(props.filters.tags ?? [])]);
const tagMode = ref<KiokuTagMode>(normalizeTagMode(props.filters.tag_mode));
const viewMode = ref<HomeViewMode>('timeline');
const draft = ref('');

watch(
    () => props.filters,
    (filters) => {
        q.value = filters.q ?? '';
        selectedTypes.value = [...filters.types];
        selectedTags.value = [...(filters.tags ?? [])];
        tagMode.value = normalizeTagMode(filters.tag_mode);
    },
);

/**
 * Voice memories waiting on an unconfigured transcription provider stay
 * 'captured' indefinitely — polling them would only end in a false timeout.
 */
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

const {
    pendingCaptures,
    markCaptureStarted,
    submitText,
    enqueueItem,
    discardRejected,
    onSynced,
} = useKiokuCaptureQueue();

const saving = ref(false);
const captureError = ref<string | null>(null);
let captureStartedAtMs: number | null = null;

const serverCaptureIds = computed(
    () =>
        new Set(
            props.memories
                .map((memory) => memory.client_capture_id)
                .filter((id): id is string => id !== null),
        ),
);

/** Device-only items awaiting server sync (hidden once the server copy shows). */
const pendingLocalCaptures = computed(() =>
    pendingCaptures.value.filter(
        (item) => !serverCaptureIds.value.has(item.clientCaptureId),
    ),
);

function manualReload(): void {
    router.reload({
        only: ['memories', 'typeCounts', 'sourceCounts', 'totalCount'],
        preserveUrl: true,
    });
}

onSynced(manualReload);

async function discardRejectedCapture(clientCaptureId: string): Promise<void> {
    const confirmed = window.confirm(
        'このキャプチャはサーバーに送れない形式です。端末から破棄しますか？原文・音声は復元できません。',
    );

    if (!confirmed) {
        return;
    }

    await discardRejected(clientCaptureId);
}

function onDraftFocus(): void {
    if (captureStartedAtMs === null && draft.value === '') {
        captureStartedAtMs = markCaptureStarted('manual');
    }
}

const recorder = useAudioRecorder({
    onAutoStop: (audio) => {
        void saveRecording(audio);
    },
});
const voiceError = ref<string | null>(null);
let voiceCaptureStartedAtMs: number | null = null;

const isRecording = computed(
    () =>
        recorder.state.value === 'recording' ||
        recorder.state.value === 'stopping',
);

async function startRecording(): Promise<void> {
    voiceError.value = null;

    if (!recorder.isSupported) {
        voiceError.value =
            'この環境では録音に対応していません。テキストで残してください。';

        return;
    }

    voiceCaptureStartedAtMs = markCaptureStarted('voice');
    const started = await recorder.start();

    if (!started) {
        voiceError.value = recorder.permissionDenied.value
            ? 'マイクが許可されていません。ブラウザ設定で許可するか、テキストで残してください。'
            : '録音を開始できませんでした。テキストで残してください。';
    }
}

async function stopRecording(): Promise<void> {
    const audio = await recorder.stop();

    if (audio !== null) {
        await saveRecording(audio);
    }
}

async function saveRecording(audio: RecordedAudio): Promise<void> {
    const item = buildCaptureQueueItem({
        clientCaptureId: crypto.randomUUID(),
        sourceType: 'voice',
        audioBlob: audio.blob,
        audioMimeType: audio.mimeType,
        durationMs: Math.max(1, Math.round(audio.durationMs)),
        capturedAt: new Date().toISOString(),
    });

    const result = await enqueueItem(item, voiceCaptureStartedAtMs);
    voiceCaptureStartedAtMs = null;

    if (result.outcome === 'failed') {
        voiceError.value = result.message;
    } else if (result.outcome === 'sent_directly') {
        manualReload();
    }
}

function discardRecording(): void {
    recorder.discard();
    voiceCaptureStartedAtMs = null;
}

async function submitDraft(): Promise<void> {
    const content = draft.value.trim();

    if (!content || saving.value) {
        return;
    }

    saving.value = true;
    captureError.value = null;

    try {
        const result = await submitText(content, captureStartedAtMs);

        if (result.outcome === 'failed') {
            captureError.value = result.message;

            return;
        }

        draft.value = '';
        captureStartedAtMs = null;

        if (result.outcome === 'sent_directly') {
            manualReload();
        }
    } finally {
        saving.value = false;
    }
}

const visibleTypeKeys = computed(() =>
    (Object.keys(MEMORY_TYPES) as MemoryTypeKey[]).filter(
        (key) =>
            (props.typeCounts[key] ?? 0) > 0 ||
            selectedTypes.value.includes(key),
    ),
);

const tagCandidates = computed(() => visibleTagCounts(props.memories, 15));

const tagGroups = computed(() => groupMemoriesByTag(props.memories));

const hasActiveFilters = computed(
    () =>
        Boolean(q.value) ||
        selectedTypes.value.length > 0 ||
        selectedTags.value.length > 0,
);

function applyFilters(): void {
    router.get(
        home.url({
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
            <div class="text-xs text-os-sub">{{ totalCount }}件の記憶</div>
        </div>

        <div
            v-if="timedOut"
            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-os-line bg-os-kioku-paper px-4 py-3 text-[13px] text-os-sub shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
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
                        <button
                            v-if="!isRecording"
                            type="button"
                            class="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-full border border-os-kioku/30 text-os-kioku transition-colors hover:bg-os-kioku-soft focus-visible:ring-2 focus-visible:ring-os-kioku/35"
                            aria-label="音声で残す（録音開始）"
                            @click="startRecording"
                        >
                            <Mic :size="15" />
                        </button>
                    </div>
                    <VoiceCaptureOverlay
                        v-if="isRecording"
                        :elapsed-ms="recorder.elapsedMs.value"
                        :max-duration-ms="KIOKU_MAX_RECORDING_MS"
                        :stopping="recorder.state.value === 'stopping'"
                        @stop="stopRecording"
                        @discard="discardRecording"
                    />
                    <p
                        v-if="voiceError"
                        class="mb-2 text-xs leading-relaxed text-[#C05A48]"
                        role="alert"
                    >
                        {{ voiceError }}
                    </p>
                    <form
                        v-if="!isRecording"
                        class="space-y-2.5"
                        @submit.prevent="submitDraft"
                    >
                        <textarea
                            v-model="draft"
                            name="raw_content"
                            rows="4"
                            required
                            placeholder="エラーメッセージ、考えたこと、URL…&#10;貼るだけ。整理はAIが後からやります。&#10;（Ctrl/⌘+Enterで保存）"
                            class="w-full resize-y rounded-xl border border-os-line bg-os-kioku-bg px-3.5 py-3 text-[13.5px] leading-relaxed text-os-ink outline-none placeholder:text-[#B3AC99] focus-visible:ring-2 focus-visible:ring-os-kioku/35"
                            @focus="onDraftFocus"
                            @keydown.meta.enter.prevent="submitDraft"
                            @keydown.ctrl.enter.prevent="submitDraft"
                        />
                        <p
                            v-if="captureError"
                            class="text-xs leading-relaxed text-[#C05A48]"
                            role="alert"
                        >
                            {{ captureError }}
                        </p>
                        <Button
                            type="submit"
                            class="h-11 w-full gap-2 rounded-xl bg-os-kioku text-[13.5px] font-bold text-white shadow-[0_3px_10px_rgba(62,86,136,0.28)] hover:bg-os-kioku/90"
                            :disabled="saving || !draft.trim()"
                            :class="draft.trim() ? 'opacity-100' : 'opacity-40'"
                        >
                            <Send :size="15" />
                            保存（AIが自動整理）
                        </Button>
                    </form>
                </section>

                <section
                    class="rounded-2xl border border-os-line bg-os-kioku-paper p-4 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
                >
                    <div
                        class="mb-2.5 text-[11.5px] font-bold tracking-wide text-os-sub"
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
                    class="rounded-2xl border border-os-line bg-os-kioku-paper p-4 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
                >
                    <div
                        class="mb-2.5 text-[11.5px] font-bold tracking-wide text-os-sub"
                    >
                        タグでしぼる
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="item in tagCandidates"
                            :key="item.tag"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-xs transition-colors"
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
                            class="inline-flex items-center gap-1 rounded-full border border-os-kioku/40 bg-os-kioku-soft px-3 py-1.5 text-xs font-bold text-os-kioku"
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

                <section
                    class="rounded-2xl border border-os-line bg-os-kioku-paper p-4 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
                >
                    <div
                        class="mb-2.5 text-[11.5px] font-bold tracking-wide text-os-sub"
                    >
                        取り込み元
                    </div>
                    <div
                        v-for="(meta, key) in SOURCE_TYPES"
                        :key="key"
                        class="flex items-center gap-2 border-b border-os-line py-1.5 text-[12.5px] last:border-0"
                        :class="meta.muted ? 'opacity-45' : ''"
                    >
                        <component
                            :is="meta.icon"
                            :size="13"
                            class="text-os-sub"
                        />
                        <span class="flex-1 text-os-ink">{{ meta.label }}</span>
                        <span class="font-mono text-xs text-os-sub">{{
                            sourceCounts[key as SourceTypeKey] || 0
                        }}</span>
                    </div>
                    <p class="mt-2.5 text-[11px] leading-relaxed text-os-sub">
                        ヨユウ・Clear
                        Dawnからの自動保存とSlack連携は、本実装ではイベント/コネクタ経由になります。
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
                        class="text-os-sub"
                        @click="
                            q = '';
                            applyFilters();
                        "
                    >
                        <X :size="14" />
                    </button>
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
                    <p
                        v-if="selectedTags.length > 0"
                        class="text-[11px] text-os-sub"
                    >
                        タグ {{ selectedTags.length }}件
                        {{
                            selectedTags.length >= 2
                                ? tagMode === 'or'
                                    ? '（OR）'
                                    : '（AND）'
                                : ''
                        }}
                    </p>
                </div>

                <KiokuLetterPreview
                    :letters="letters"
                    :test-letters="testLetters ?? []"
                />

                <div
                    v-for="item in pendingLocalCaptures"
                    :key="item.clientCaptureId"
                    class="rounded-2xl border border-dashed border-os-kioku/40 bg-os-kioku-paper px-4 py-3 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
                    role="status"
                >
                    <div
                        class="mb-1 flex items-center gap-1.5 text-[11.5px] font-bold text-os-sub"
                    >
                        <CloudOff :size="13" />
                        端末に保存済み・同期待ち
                        <span v-if="item.rejected">（送信できない形式）</span>
                        <span v-else-if="item.retryCount > 0"
                            >（再試行 {{ item.retryCount }}回目待ち）</span
                        >
                    </div>
                    <p
                        class="line-clamp-2 text-[13px] leading-relaxed text-os-ink"
                    >
                        {{
                            item.sourceType === 'voice'
                                ? `音声メモ（${Math.round((item.durationMs ?? 0) / 1000)}秒）`
                                : item.rawContent
                        }}
                    </p>
                    <button
                        v-if="item.rejected"
                        type="button"
                        class="mt-2 text-[11.5px] font-bold text-[#C05A48] hover:underline"
                        @click="discardRejectedCapture(item.clientCaptureId)"
                    >
                        端末から破棄
                    </button>
                </div>

                <div
                    v-if="
                        memories.length === 0 &&
                        pendingLocalCaptures.length === 0
                    "
                    class="rounded-2xl border border-os-line bg-os-kioku-paper p-9 text-center shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
                >
                    <Brain :size="26" class="mx-auto mb-2.5 text-os-faint" />
                    <p class="text-[13px] leading-relaxed text-os-sub">
                        {{
                            hasActiveFilters
                                ? '条件に一致する記憶はありません。'
                                : 'まだ記憶がありません。左の保存ボックスからどうぞ。'
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
                        class="rounded-2xl border border-os-line bg-os-kioku-paper p-9 text-center shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
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
                            <h2
                                class="text-[12.5px] font-bold tracking-wide"
                                :class="
                                    group.untagged
                                        ? 'text-os-sub'
                                        : 'text-os-kioku'
                                "
                            >
                                {{
                                    group.untagged ? group.tag : `#${group.tag}`
                                }}
                            </h2>
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

                <p
                    class="pt-2 text-center text-[11px] leading-relaxed text-os-sub"
                >
                    保存は即時、AI整理（分類・要約・構造化）は非同期。Laravel
                    Queue で同じ流れです。
                </p>
            </section>
        </div>
    </div>
</template>
