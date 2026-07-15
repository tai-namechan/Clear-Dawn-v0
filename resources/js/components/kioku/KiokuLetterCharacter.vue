<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { kiokuLetterCharacter } from '@/lib/kiokuLetterCharacters';
import type { KiokuLetterCharacterVariant } from '@/types/kiokuLetter';

const props = defineProps<{
    variant: KiokuLetterCharacterVariant;
    forceFail?: boolean;
}>();

const failed = ref(props.forceFail === true);

watch(
    () => [props.variant, props.forceFail] as const,
    () => {
        failed.value = props.forceFail === true;
    },
);

const character = computed(() => kiokuLetterCharacter(props.variant));
</script>

<template>
    <!-- Decorative figure: the letter body, memory links and verdict UI never
         depend on this image loading. Fixed dimensions keep layout stable. -->
    <figure class="m-0 w-full">
        <img
            v-if="!failed"
            :src="character.asset"
            alt=""
            aria-hidden="true"
            loading="lazy"
            decoding="async"
            :width="character.width"
            :height="character.height"
            class="h-auto w-full select-none"
            @error="failed = true"
        />
        <div
            v-else
            class="w-full rounded-2xl bg-(--letter-accent-soft)"
            :style="{ aspectRatio: `${character.width} / ${character.height}` }"
            aria-hidden="true"
        ></div>
        <figcaption
            class="mt-1.5 text-center text-[11.5px] font-bold tracking-wide text-(--letter-accent)"
        >
            {{ character.name }}
        </figcaption>
    </figure>
</template>
