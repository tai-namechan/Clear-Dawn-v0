<script setup lang="ts">
defineEmits<{
    (e: 'click'): void;
}>();
</script>

<template>
    <button
        type="button"
        class="inline-flex min-w-[9.5rem] items-center justify-center rounded-full border border-cd-gilt/70 bg-cd-surface/88 px-10 py-3.5 font-serif text-lg tracking-[0.18em] text-cd-dawn-deep shadow-[0_8px_28px_hsl(237_30%_20%_/_0.18)] backdrop-blur-[3px] transition-[background-color,border-color,box-shadow,transform] duration-300 hover:border-cd-gilt hover:bg-cd-surface hover:shadow-[0_10px_32px_hsl(237_30%_20%_/_0.22)] focus-visible:ring-2 focus-visible:ring-cd-gilt/60 focus-visible:ring-offset-2 focus-visible:ring-offset-transparent focus-visible:outline-none md:min-w-[11rem] md:px-12 md:py-4 md:text-xl"
        data-test="start-button"
        @click="$emit('click')"
    >
        はじめる
    </button>
</template>
