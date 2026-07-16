<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { kiokuLetterCharacter } from '@/lib/kiokuLetterCharacters';
import type { KiokuLetterCharacterVariant } from '@/types/kiokuLetter';

const props = defineProps<{
    variant: KiokuLetterCharacterVariant;
    forceFail?: boolean;
    /** Stationery-integrated portrait: no caption, soft fade into the paper. */
    embedded?: boolean;
}>();

const failed = ref(props.forceFail === true);

watch(
    () => [props.variant, props.forceFail] as const,
    () => {
        failed.value = props.forceFail === true;
    },
);

const character = computed(() => kiokuLetterCharacter(props.variant));

const imageClass = computed(() =>
    props.embedded
        ? // Soft left/bottom dissolve into the paper (no hard card edge).
          'h-auto w-full select-none [mask-image:linear-gradient(to_left,black_68%,transparent_100%),linear-gradient(to_top,transparent_0%,black_14%,black_100%)] [mask-composite:intersect] [-webkit-mask-image:linear-gradient(to_left,black_68%,transparent_100%),linear-gradient(to_top,transparent_0%,black_14%,black_100%)] [-webkit-mask-composite:source-in]'
        : 'h-auto w-full select-none',
);
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
            :class="imageClass"
            @error="failed = true"
        />
        <div
            v-else
            class="w-full rounded-2xl bg-(--letter-accent-soft)"
            :class="embedded ? 'opacity-70' : ''"
            :style="{ aspectRatio: `${character.width} / ${character.height}` }"
            aria-hidden="true"
        ></div>
        <figcaption
            v-if="!embedded"
            class="mt-1.5 text-center text-[11.5px] font-bold tracking-wide text-(--letter-accent)"
        >
            {{ character.name }}
        </figcaption>
    </figure>
</template>
