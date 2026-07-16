<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowUpRight, CheckCircle2, Mail } from '@lucide/vue';
import { computed, ref } from 'vue';
import KiokuLetterCharacter from '@/components/kioku/KiokuLetterCharacter.vue';
import KiokuLetterVerdict from '@/components/kioku/KiokuLetterVerdict.vue';
import { Button } from '@/components/ui/button';
import {
    KIOKU_LETTER_EMPTY_MESSAGE,
    KIOKU_LETTER_EMPTY_MESSAGE_DAILY,
    kiokuLetterCharacterMeta,
    kiokuLetterTitleLabel,
} from '@/lib/kiokuLetter.mjs';
import { complete } from '@/routes/kioku/letters';
import { show as showMemory } from '@/routes/kioku/memories';
import type { KiokuLetter } from '@/types/kiokuLetter';

const props = defineProps<{
    letter: KiokuLetter;
    preview?: boolean;
}>();

const completing = ref(false);

const character = computed(() =>
    kiokuLetterCharacterMeta(props.letter.character_variant),
);

const isCompleted = computed(() => props.letter.completed_at !== null);
const isPreview = computed(() => props.preview === true);

const allJudged = computed(
    () =>
        props.letter.items.length > 0 &&
        props.letter.verdict_counts.judged === props.letter.items.length,
);

const emptyMessage = computed(() =>
    props.letter.cadence === 'daily'
        ? KIOKU_LETTER_EMPTY_MESSAGE_DAILY
        : KIOKU_LETTER_EMPTY_MESSAGE,
);

const title = computed(() => kiokuLetterTitleLabel(props.letter));

const isEmptyLetter = computed(
    () =>
        !['failed', 'generating'].includes(props.letter.status) &&
        props.letter.items.length === 0,
);

function requestComplete(): void {
    if (isPreview.value || completing.value || isCompleted.value) {
        return;
    }

    const canCompleteEmpty =
        props.letter.items.length === 0 &&
        ['empty', 'published', 'opened'].includes(props.letter.status);

    if (!allJudged.value && !canCompleteEmpty) {
        return;
    }

    completing.value = true;
    router.post(
        complete.url(props.letter.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                completing.value = false;
            },
        },
    );
}
</script>

<template>
    <!-- One stationery surface: character fades into the paper on the right
         (docs/product/kioku-final-remaining-implementation.md §15.2).
         Dividers stay on the copy column only so they never cut the figure. -->
    <section
        class="relative overflow-hidden rounded-[20px] border border-os-line bg-os-kioku-paper shadow-[0_8px_28px_rgba(43,41,36,0.1)]"
    >
        <div
            class="pointer-events-none absolute right-0 bottom-0 z-20 hidden w-[46%] max-w-[300px] md:block lg:w-[40%]"
            aria-hidden="true"
        >
            <KiokuLetterCharacter
                embedded
                :variant="letter.character_variant"
                :force-fail="letter.force_image_fail === true"
            />
        </div>

        <div class="relative z-10 min-w-0">
            <header
                class="space-y-2 px-5 pt-4 pb-3 sm:px-6 md:pr-[42%] lg:pr-[40%]"
            >
                <div
                    class="flex items-center gap-1.5 text-[11.5px] font-bold tracking-wide text-(--letter-accent)"
                >
                    <Mail :size="13" />
                    {{ title }}
                </div>
                <h1 class="text-lg font-bold text-os-ink">
                    <template v-if="letter.cadence === 'daily'">
                        {{ letter.delivery_date ?? letter.week_start }}
                        の記憶から
                    </template>
                    <template v-else>
                        {{ letter.week_start }} 〜
                        {{ letter.week_end }} の記憶から
                    </template>
                </h1>
                <p
                    v-if="letter.intro"
                    class="text-[13.5px] leading-relaxed text-os-ink"
                >
                    {{ letter.intro }}
                </p>
                <div
                    class="mt-3 h-px w-[min(100%,18rem)] bg-(--letter-accent-soft)"
                    aria-hidden="true"
                />
            </header>

            <div
                class="px-5 py-4 sm:px-6 md:pr-[42%] lg:pr-[40%]"
                :class="isEmptyLetter ? '' : 'space-y-5'"
            >
                <p
                    v-if="letter.status === 'failed'"
                    class="py-6 text-[13.5px] leading-relaxed text-[#C05A48]"
                >
                    この手紙の生成に失敗しました。この期間の手紙は作られていません。
                </p>
                <p
                    v-else-if="letter.status === 'generating'"
                    class="py-6 text-[13.5px] leading-relaxed text-os-sub"
                >
                    手紙を生成しています…
                </p>
                <!-- Empty copy starts under the title (letter body), with the
                     character filling the right so we avoid a tall empty void. -->
                <div
                    v-else-if="isEmptyLetter"
                    class="max-w-[24rem] space-y-4 pt-1 pb-10 md:min-h-[240px] md:pb-16"
                >
                    <p class="text-[14px] leading-relaxed text-os-sub">
                        {{ emptyMessage }}
                    </p>
                    <Button
                        v-if="!isCompleted"
                        type="button"
                        class="h-10 w-fit rounded-xl bg-(--letter-accent) text-[13px] font-bold text-white hover:bg-(--letter-accent-deep) disabled:opacity-50"
                        :disabled="completing || isPreview"
                        @click="requestComplete"
                    >
                        確認した
                    </Button>
                </div>

                <template v-else>
                    <article
                        v-for="item in letter.items"
                        :key="item.id"
                        class="space-y-2.5 border-b border-os-line pb-5 last:border-0 last:pb-0"
                    >
                        <h2
                            class="flex gap-2 text-[15px] font-bold text-os-ink"
                        >
                            <span class="text-(--letter-accent)"
                                >{{ item.position }}.</span
                            >
                            <span>{{ item.headline }}</span>
                        </h2>

                        <div
                            class="rounded-xl bg-(--letter-accent-soft) px-3.5 py-2.5 text-[13px] leading-relaxed text-os-ink"
                        >
                            <span
                                class="mb-0.5 block text-[10.5px] font-bold tracking-wide text-(--letter-accent-deep)"
                                >なぜ今</span
                            >
                            {{ item.why_now }}
                        </div>

                        <div
                            class="flex flex-wrap items-center gap-x-4 gap-y-1"
                        >
                            <Link
                                v-if="!isPreview"
                                :href="showMemory.url(item.memory_id)"
                                class="inline-flex items-center gap-1 text-[12.5px] font-bold text-(--letter-accent) underline-offset-2 hover:underline"
                            >
                                元の記憶を開く（{{ item.title }}）
                                <ArrowUpRight :size="12" />
                            </Link>
                            <span
                                v-else
                                class="text-[12.5px] font-bold text-(--letter-accent)"
                            >
                                元の記憶を開く（{{ item.title }}）
                            </span>
                            <Link
                                v-for="related in item.related"
                                :key="related.id"
                                :href="showMemory.url(related.id)"
                                class="text-[11.5px] text-os-sub underline-offset-2 hover:underline"
                            >
                                関連: {{ related.title ?? 'ひらく' }}
                            </Link>
                        </div>

                        <KiokuLetterVerdict
                            :letter-id="letter.id"
                            :item="item"
                            :disabled="isCompleted || isPreview"
                        />
                    </article>

                    <div
                        v-if="letter.status === 'halted'"
                        class="rounded-xl bg-[#F8E9E4] px-3.5 py-2.5 text-[12.5px] leading-relaxed text-[#C05A48]"
                        role="status"
                    >
                        表示すべきでない記憶が報告されたため、手紙の生成は停止しています。除外条件を直すまで次の手紙は作られません。
                    </div>

                    <div
                        v-if="letter.items.length > 0"
                        class="flex flex-wrap items-center justify-between gap-3 pt-1"
                    >
                        <p
                            v-if="isCompleted"
                            class="inline-flex items-center gap-1.5 text-[12.5px] font-bold text-(--letter-accent)"
                        >
                            <CheckCircle2 :size="14" />
                            評価済み: HIT {{ letter.verdict_counts.hit }} /
                            {{ letter.items.length }}件
                        </p>
                        <p v-else class="text-[12px] text-os-sub" role="status">
                            {{ letter.items.length }}件中{{
                                letter.verdict_counts.judged
                            }}件を判定済み
                        </p>
                        <Button
                            v-if="!isCompleted && !isPreview"
                            type="button"
                            class="h-10 rounded-xl bg-(--letter-accent) text-[13px] font-bold text-white hover:bg-(--letter-accent-deep) disabled:opacity-40"
                            :disabled="!allJudged || completing"
                            @click="requestComplete"
                        >
                            評価を完了して記録する
                        </Button>
                    </div>
                </template>
            </div>

            <div
                class="flex justify-end px-5 pt-1 pb-2 md:hidden"
                aria-hidden="true"
            >
                <div class="w-36 max-w-[45%]">
                    <KiokuLetterCharacter
                        embedded
                        :variant="letter.character_variant"
                        :force-fail="letter.force_image_fail === true"
                    />
                </div>
            </div>

            <footer class="px-5 py-3.5 text-right sm:px-6 md:pr-6">
                <p
                    class="text-[12.5px] font-bold tracking-wide text-(--letter-accent)"
                >
                    {{ character.signature }}
                </p>
            </footer>
        </div>
    </section>
</template>
