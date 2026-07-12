<script setup lang="ts">
import { Mic, Square, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { formatRecordingElapsed } from '@/lib/kiokuAudioRecorder.mjs';

const props = defineProps<{
    elapsedMs: number;
    maxDurationMs: number;
    stopping: boolean;
}>();

const emit = defineEmits<{
    stop: [];
    discard: [];
}>();

const confirmingDiscard = ref(false);

const elapsedLabel = computed(() => formatRecordingElapsed(props.elapsedMs));
const maxLabel = computed(() => formatRecordingElapsed(props.maxDurationMs));

function requestDiscard(): void {
    if (!confirmingDiscard.value) {
        confirmingDiscard.value = true;

        return;
    }

    confirmingDiscard.value = false;
    emit('discard');
}
</script>

<template>
    <div
        class="rounded-2xl border border-[#C05A48]/40 bg-os-kioku-paper p-4 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
        role="status"
        aria-live="polite"
    >
        <div class="flex items-center gap-2.5">
            <span
                class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[#F8E9E4] text-[#C05A48] motion-safe:animate-pulse"
                aria-hidden="true"
            >
                <Mic :size="15" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[12.5px] font-bold text-[#C05A48]">録音中</p>
                <p class="font-mono text-xs text-os-sub">
                    {{ elapsedLabel }} / {{ maxLabel }}（3分で自動保存）
                </p>
            </div>
        </div>

        <div class="mt-3 flex items-center gap-2">
            <Button
                type="button"
                class="h-11 flex-1 gap-2 rounded-xl bg-[#C05A48] text-[13.5px] font-bold text-white hover:bg-[#C05A48]/90"
                :disabled="stopping"
                @click="emit('stop')"
            >
                <Square :size="14" />
                {{ stopping ? '保存中…' : '停止して保存' }}
            </Button>
            <Button
                type="button"
                variant="outline"
                class="h-11 gap-1.5 rounded-xl border-os-line px-3 text-xs text-os-sub"
                :disabled="stopping"
                @click="requestDiscard"
            >
                <Trash2 :size="13" />
                {{ confirmingDiscard ? '本当に破棄する' : '破棄' }}
            </Button>
        </div>
    </div>
</template>
