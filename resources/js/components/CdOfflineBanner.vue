<script setup lang="ts">
import type { PluginListenerHandle } from '@capacitor/core';
import { WifiOff } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Shows a calm notice when the connection drops while the app is open
 * (docs/product/ios-app.md). Works in the browser too via the plugin's
 * web implementation (navigator.onLine).
 */
const offline = ref(false);
let listener: PluginListenerHandle | null = null;

onMounted(async () => {
    try {
        // Loaded after the first paint so the plugin stays out of the entry bundle.
        const { Network } = await import('@capacitor/network');

        offline.value = !(await Network.getStatus()).connected;
        listener = await Network.addListener(
            'networkStatusChange',
            (status) => {
                offline.value = !status.connected;
            },
        );
    } catch {
        // Without the plugin there is nothing to show.
    }
});

onUnmounted(() => {
    void listener?.remove();
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
