<script setup lang="ts">
import { Clapperboard, Play } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { apiFetch } from '@/lib/apiFetch';
import type { Video } from '@/types/routine';

interface Props {
    video: Video;
}

const props = defineProps<Props>();

const previewUrl = ref<string | null>(null);
const loading = ref(false);
let requestId = 0;

async function loadPreview(): Promise<void> {
    const current = ++requestId;

    previewUrl.value = null;

    if (props.video.status !== 'ready') {
        return;
    }

    loading.value = true;

    try {
        const result = await apiFetch<{ url: string }>(
            `/videos/${props.video.id}/stream-url`,
        );

        if (current === requestId) {
            previewUrl.value = result.url;
        }
    } catch {
        if (current === requestId) {
            previewUrl.value = null;
        }
    } finally {
        if (current === requestId) {
            loading.value = false;
        }
    }
}

watch(
    () => props.video.id,
    () => {
        void loadPreview();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    requestId += 1;
});

/** Seek slightly so browsers paint a visible frame (metadata alone is often black). */
function onLoadedMetadata(event: Event): void {
    const el = event.target;

    if (!(el instanceof HTMLVideoElement)) {
        return;
    }

    if (el.readyState >= 1 && el.duration > 0.1) {
        el.currentTime = Math.min(0.1, el.duration / 2);
    }
}
</script>

<template>
    <div
        class="relative flex aspect-video w-full items-center justify-center overflow-hidden bg-cd-dawn-soft/20"
    >
        <video
            v-if="previewUrl"
            :src="previewUrl"
            class="h-full w-full object-cover"
            muted
            playsinline
            preload="metadata"
            @loadedmetadata="onLoadedMetadata"
        />
        <Clapperboard
            v-else
            :size="32"
            :stroke-width="1.4"
            class="text-cd-ink-muted/50"
            :class="{ 'opacity-40': loading }"
        />
        <Play
            v-if="video.status === 'ready'"
            :size="20"
            :stroke-width="1.6"
            class="absolute text-primary opacity-0 transition-opacity group-hover:opacity-100"
        />
    </div>
</template>
