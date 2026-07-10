<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: '今日を回す場所',
    },
});
</script>

<template>
    <div class="text-center">
        <Head title="ヨユウ" />
        <p class="text-sm font-medium tracking-wide text-os-yoyu">ヨユウ</p>
        <h2 class="mt-2 text-2xl font-semibold text-foreground">準備中</h2>
        <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
            今日を焦らず回す場所です。実装はキオクのあとで進めます。
        </p>
    </div>
</template>
