<script setup lang="ts">
import HeroBackground from '@/components/landing/HeroBackground.vue';
import HeroContent from '@/components/landing/HeroContent.vue';
import LandingHeader from '@/components/landing/LandingHeader.vue';

defineEmits<{
    (e: 'open-login'): void;
    (e: 'open-register'): void;
}>();
</script>

<template>
    <section
        aria-label="Clear Dawn 公開ランディング"
        class="relative flex min-h-dvh flex-col overflow-hidden"
    >
        <HeroBackground />
        <LandingHeader
            @open-login="$emit('open-login')"
            @open-register="$emit('open-register')"
        />
        <HeroContent @open-login="$emit('open-login')" />
    </section>
</template>
