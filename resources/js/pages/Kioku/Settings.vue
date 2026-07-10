<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, settings, sources } from '@/routes/kioku';

defineOptions({
    layout: {
        title: 'キオク',
        subtitle: '設定',
    },
});
</script>

<template>
    <div class="space-y-6">
        <Head title="設定 — キオク" />
        <nav class="flex flex-wrap gap-2 text-sm">
            <Link
                :href="home()"
                class="rounded-full border border-border px-3 py-1.5 text-muted-foreground hover:bg-muted"
                >記憶</Link
            >
            <Link
                :href="sources()"
                class="rounded-full border border-border px-3 py-1.5 text-muted-foreground hover:bg-muted"
                >取り込み元</Link
            >
            <Link
                :href="settings()"
                class="rounded-full bg-os-kioku px-3 py-1.5 font-medium text-white"
                >設定</Link
            >
        </nav>
        <div
            class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
        >
            エクスポート・削除ポリシー・機微フラグ既定値は後続マイルストーンで追加します。
        </div>
    </div>
</template>
