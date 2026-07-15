<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, onMounted } from 'vue';
import KiokuLetterCharacter from '@/components/kioku/KiokuLetterCharacter.vue';
import KiokuLetterPaper from '@/components/kioku/KiokuLetterPaper.vue';
import { kiokuLetterCharacterCssVars, kiokuLetterTitleLabel } from '@/lib/kiokuLetter.mjs';
import { home } from '@/routes/kioku';
import { open } from '@/routes/kioku/letters';
import type { KiokuLetter } from '@/types/kiokuLetter';

const props = defineProps<{
    letter: KiokuLetter;
    preview?: boolean;
}>();

const isPreview = computed(() => props.preview === true);

const cssVars = computed(() =>
    kiokuLetterCharacterCssVars(props.letter.character_variant),
);

const pageTitle = computed(() => kiokuLetterTitleLabel(props.letter));

/**
 * First view records the open (idempotent server-side). Preview never POSTs.
 * Claim is based on opened_at, so evaluating/halted can still record the open.
 */
onMounted(() => {
    if (isPreview.value || props.letter.opened_at !== null) {
        return;
    }

    if (
        [
            'published',
            'empty',
            'evaluating',
            'halted',
            'opened',
        ].includes(props.letter.status)
    ) {
        router.post(open.url(props.letter.id), {}, { preserveScroll: true });
    }
});

defineOptions({
    layout: {
        title: 'キオク',
        subtitle: 'キオク便り',
    },
});
</script>

<template>
    <div
        class="mx-auto max-w-[960px] space-y-4"
        :data-character="letter.character_variant"
        :style="cssVars"
    >
        <Head :title="pageTitle" />

        <Link
            :href="home()"
            class="inline-flex items-center gap-1 text-sm text-os-sub hover:text-os-ink"
        >
            <ArrowLeft :size="14" />
            キオクへ戻る
        </Link>

        <div
            v-if="isPreview"
            class="rounded-xl bg-[#F8E9E4] px-3.5 py-2.5 text-[13px] font-bold text-[#C05A48]"
            role="status"
        >
            [プレビュー] 表示確認用です。AI・DB・判定保存は行われません。
        </div>
        <div
            v-else-if="letter.mode === 'test'"
            class="rounded-xl bg-os-kioku-soft px-3.5 py-2.5 text-[13px] font-bold text-os-kioku"
            role="status"
        >
            [テスト便り] 実験指標・cooldownには影響しません。
        </div>

        <div
            class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,68fr)_minmax(0,32fr)] lg:items-start lg:gap-6"
        >
            <div
                class="order-1 flex justify-end lg:sticky lg:top-5 lg:order-2 lg:block"
            >
                <div class="w-28 sm:w-36 lg:w-full lg:max-w-[280px]">
                    <KiokuLetterCharacter
                        :variant="letter.character_variant"
                        :force-fail="letter.force_image_fail === true"
                    />
                </div>
            </div>

            <div class="order-2 min-w-0 lg:order-1">
                <KiokuLetterPaper :letter="letter" :preview="isPreview" />
            </div>
        </div>
    </div>
</template>
