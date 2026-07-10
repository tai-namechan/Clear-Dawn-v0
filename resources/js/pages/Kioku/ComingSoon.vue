<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineOptions({
    layout: {
        title: 'キオク',
        subtitle: '記憶の保存・検索・想起',
    },
});
</script>

<template>
    <div class="text-center">
        <Head title="キオク" />
        <p class="text-sm font-medium tracking-wide text-os-kioku">キオク</p>
        <h2 class="mt-2 text-2xl font-semibold text-foreground">準備中</h2>
        <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
            記憶の保存・検索・想起の場所です。次のマイルストーンで実装します。
        </p>
    </div>
</template>
