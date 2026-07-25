<script setup lang="ts">
import { ref, watch } from 'vue';

type VideoOrientation = 'landscape' | 'portrait';

interface Props {
    src?: string | null;
    loading?: boolean;
    emptyLabel?: string;
}

const props = withDefaults(defineProps<Props>(), {
    src: null,
    loading: false,
    emptyLabel: 'このステップには動画がありません',
});

const orientation = ref<VideoOrientation>('landscape');

watch(
    () => props.src,
    () => {
        // Keep landscape shell until metadata arrives to limit layout jump.
        orientation.value = 'landscape';
    },
);

function onLoadedMetadata(event: Event): void {
    const video = event.target as HTMLVideoElement;
    const width = video.videoWidth;
    const height = video.videoHeight;

    if (!width || !height) {
        return;
    }

    orientation.value = width >= height ? 'landscape' : 'portrait';
}
</script>

<template>
    <div
        class="exercise-video-shell border border-cd-line shadow-sm"
        :class="
            orientation === 'portrait' ? 'is-portrait' : 'is-landscape'
        "
        :data-orientation="orientation"
    >
        <div
            v-if="loading"
            class="exercise-video-shell__placeholder"
            role="status"
        >
            動画を読み込み中…
        </div>
        <video
            v-else-if="src"
            :src="src"
            class="exercise-video-shell__media"
            controls
            playsinline
            @loadedmetadata="onLoadedMetadata"
        />
        <div v-else class="exercise-video-shell__placeholder">
            {{ emptyLabel }}
        </div>
    </div>
</template>
