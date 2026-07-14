<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronRight,
    Clock,
    Compass,
    Pause,
    Play,
    RefreshCw,
    Sparkles,
    Sun,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import SourceBadge from '@/components/kioku/SourceBadge.vue';
import TypeChip from '@/components/kioku/TypeChip.vue';
import { Button } from '@/components/ui/button';
import {
    formatKiokuAudioClock,
    kiokuAudioDurationSeconds,
} from '@/lib/kiokuAudioDuration.mjs';
import { kiokuMemoryDisplayTitle } from '@/lib/kiokuMemoryCard.mjs';
import { formatAgo, sourceTypeMeta } from '@/lib/kiokuMeta';
import { kiokuTranscriptDisplayMode } from '@/lib/kiokuTranscriptDisplay.mjs';
import { home } from '@/routes/kioku';
import {
    audio,
    reenrich,
    retryTranscription,
    show,
} from '@/routes/kioku/memories';
import type { KiokuMemory } from '@/types/kioku';

interface Props {
    memory: KiokuMemory;
    related: KiokuMemory[];
    transcriptionEnabled: boolean;
    audioDurationMs?: number | null;
}

const props = defineProps<Props>();

const audioMissing = ref(false);
const audioEl = ref<HTMLAudioElement | null>(null);
const playing = ref(false);
const currentSeconds = ref(0);

const declaredDurationSeconds = computed(() =>
    kiokuAudioDurationSeconds(props.audioDurationMs),
);

const currentClock = computed(() => formatKiokuAudioClock(currentSeconds.value));

const totalClock = computed(() => {
    if (declaredDurationSeconds.value !== null) {
        return formatKiokuAudioClock(declaredDurationSeconds.value);
    }

    return '--:--';
});

const progressRatio = computed(() => {
    const total = declaredDurationSeconds.value;

    if (total === null || total <= 0) {
        return 0;
    }

    return Math.min(1, Math.max(0, currentSeconds.value / total));
});

watch(
    () => props.memory.id,
    () => {
        audioMissing.value = false;
        playing.value = false;
        currentSeconds.value = 0;
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

const titleClass = computed(
    () => sourceTypeMeta(props.memory.source_type).titleClass ?? 'text-os-ink',
);

function onAudioError(): void {
    audioMissing.value = true;
    playing.value = false;
}

function onAudioTimeUpdate(): void {
    currentSeconds.value = audioEl.value?.currentTime ?? 0;
}

function onAudioEnded(): void {
    playing.value = false;
    const total = declaredDurationSeconds.value;

    if (total !== null) {
        currentSeconds.value = total;
    }
}

async function toggleAudioPlayback(): Promise<void> {
    const el = audioEl.value;

    if (el === null || audioMissing.value) {
        return;
    }

    if (el.paused) {
        try {
            await el.play();
            playing.value = true;
        } catch {
            audioMissing.value = true;
            playing.value = false;
        }

        return;
    }

    el.pause();
    playing.value = false;
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
                    <div
                        v-else
                        class="flex items-center gap-3 rounded-full border border-os-line bg-os-kioku-soft/40 px-3 py-2"
                    >
                        <audio
                            ref="audioEl"
                            preload="metadata"
                            class="hidden"
                            :src="audio.url(memory.id)"
                            @error="onAudioError"
                            @timeupdate="onAudioTimeUpdate"
                            @ended="onAudioEnded"
                            @pause="playing = false"
                            @play="playing = true"
                        ></audio>
                        <button
                            type="button"
                            class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-os-kioku text-os-kioku-soft transition-opacity hover:opacity-90"
                            :aria-label="playing ? '一時停止' : '再生'"
                            @click="toggleAudioPlayback"
                        >
                            <Pause v-if="playing" :size="14" />
                            <Play v-else :size="14" class="translate-x-px" />
                        </button>
                        <div class="min-w-0 flex-1 space-y-1">
                            <div
                                class="h-1 overflow-hidden rounded-full bg-os-line"
                                aria-hidden="true"
                            >
                                <div
                                    class="h-full rounded-full bg-os-kioku transition-[width] duration-100"
                                    :style="{
                                        width: `${progressRatio * 100}%`,
                                    }"
                                />
                            </div>
                            <div
                                class="font-mono text-[11px] tracking-wide text-os-sub"
                            >
                                {{ currentClock }} / {{ totalClock }}
                            </div>
                        </div>
                    </div>
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
                    <p
                        v-else
                        class="text-[12.5px] leading-relaxed text-os-sub"
                    >
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
                        <span
                            class="flex-1 text-[12.5px] font-medium"
                            :class="
                                sourceTypeMeta(item.source_type).titleClass ??
                                'text-os-ink'
                            "
                            >{{ kiokuMemoryDisplayTitle(item) }}</span
                        >
                        <ChevronRight :size="14" class="text-os-faint" />
                    </Link>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 border-t border-os-line px-5 py-4">
                <Button
                    type="button"
                    class="gap-1.5 rounded-full border border-[#12948844] bg-[#E4F4F2] text-[#129488] hover:bg-[#E4F4F2]"
                    variant="outline"
                    @click="
                        toast.message('ヨユウのタスクに送信しました（モック）')
                    "
                >
                    <Sun :size="13" />
                    ヨユウのタスクへ
                </Button>
                <Button
                    type="button"
                    class="gap-1.5 rounded-full border border-[#5C4E8E44] bg-[#EDEAF5] text-[#5C4E8E] hover:bg-[#EDEAF5]"
                    variant="outline"
                    @click="
                        toast.message(
                            'Clear Dawnの目標に紐づけました（モック）',
                        )
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
