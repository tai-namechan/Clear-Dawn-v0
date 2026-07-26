<script setup lang="ts">
withDefaults(
    defineProps<{
        message?: string;
    }>(),
    {
        message:
            '読み取り専用のプロトタイプです。登録・編集・承認操作は対象外です。',
    },
);
</script>

<template>
    <div
        class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
        role="note"
    >
        {{ message }}
    </div>
</template>
