<script setup lang="ts">
import StartButton from '@/components/landing/StartButton.vue';

defineEmits<{
    (e: 'open-login'): void;
}>();
</script>

<template>
    <div
        class="relative z-10 flex flex-1 flex-col items-center justify-center px-6 pt-[46vh] pb-[16vh] md:pt-[44vh] md:pb-[14vh]"
    >
        <StartButton @click="$emit('open-login')" />
    </div>
</template>
