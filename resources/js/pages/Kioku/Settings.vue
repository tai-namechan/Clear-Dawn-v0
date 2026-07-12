<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineOptions({
    layout: {
        title: 'キオク',
        subtitle: '設定',
    },
});
</script>

<template>
    <div class="space-y-5">
        <Head title="設定 — キオク" />
        <div
            class="mx-auto max-w-lg rounded-2xl border border-dashed border-os-line bg-os-kioku-paper p-10 text-center text-sm leading-relaxed text-os-sub"
        >
            エクスポート・削除ポリシー・機微フラグ既定値は後続マイルストーンで追加します。
        </div>
    </div>
</template>
