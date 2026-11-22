<script setup lang="ts">
import { WifiOff } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Shows a calm notice when the connection drops while the app is open
 * (docs/product/ios-app.md). Uses the browser's online/offline events,
 * which the iOS WebView supports, so no native plugin is needed.
 */
const offline = ref(false);

function update(): void {
    offline.value = !navigator.onLine;
}

onMounted(() => {
    update();
    window.addEventListener('online', update);
    window.addEventListener('offline', update);
});

onUnmounted(() => {
    window.removeEventListener('online', update);
    window.removeEventListener('offline', update);
});
</script>

<template>
    <div
        v-if="offline"
        class="cd-offline-banner"
        role="status"
        aria-live="polite"
    >
        <WifiOff :size="16" :stroke-width="1.8" aria-hidden="true" />
        <span>オフラインです。接続が戻ると、そのまま続けられます。</span>
    </div>
</template>
