<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, onMounted } from 'vue';
import KiokuLetterCharacter from '@/components/kioku/KiokuLetterCharacter.vue';
import KiokuLetterPaper from '@/components/kioku/KiokuLetterPaper.vue';
import { kiokuLetterCharacterCssVars } from '@/lib/kiokuLetter.mjs';
import { home } from '@/routes/kioku';
import { open } from '@/routes/kioku/letters';
import type { KiokuLetter } from '@/types/kiokuLetter';

const props = defineProps<{
    letter: KiokuLetter;
}>();

const cssVars = computed(() =>
    kiokuLetterCharacterCssVars(props.letter.character_variant),
);

/**
 * First view records the open (idempotent server-side: a reload never
 * double-counts references).
 */
onMounted(() => {
    if (
        props.letter.opened_at === null &&
        ['published', 'empty'].includes(props.letter.status)
    ) {
        router.post(open.url(props.letter.id), {}, { preserveScroll: true });
    }
});

defineOptions({
    layout: {
        title: 'キオク',
        subtitle: '今週のキオク便り',
    },
});
</script>

<template>
    <div
        class="mx-auto max-w-[960px] space-y-4"
        :data-character="letter.character_variant"
        :style="cssVars"
    >
        <Head title="今週のキオク便り" />

        <Link
            :href="home()"
            class="inline-flex items-center gap-1 text-sm text-os-sub hover:text-os-ink"
        >
            <ArrowLeft :size="14" />
            キオクへ戻る
        </Link>

        <div
            class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,68fr)_minmax(0,32fr)] lg:items-start lg:gap-6"
        >
            <!-- Mobile: small figure top-right, never overlapping the body.
                 Desktop: right column ~32%. Same single image element. -->
            <div
                class="order-1 flex justify-end lg:sticky lg:top-5 lg:order-2 lg:block"
            >
                <div class="w-28 sm:w-36 lg:w-full lg:max-w-[280px]">
                    <KiokuLetterCharacter :variant="letter.character_variant" />
                </div>
            </div>

            <div class="order-2 min-w-0 lg:order-1">
                <KiokuLetterPaper :letter="letter" />
            </div>
        </div>
    </div>
</template>
