<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowUpRight, CheckCircle2, Mail } from '@lucide/vue';
import { computed, ref } from 'vue';
import KiokuLetterVerdict from '@/components/kioku/KiokuLetterVerdict.vue';
import { Button } from '@/components/ui/button';
import {
    KIOKU_LETTER_EMPTY_MESSAGE,
    kiokuLetterCharacterMeta,
} from '@/lib/kiokuLetter.mjs';
import { complete } from '@/routes/kioku/letters';
import { show as showMemory } from '@/routes/kioku/memories';
import type { KiokuLetter } from '@/types/kiokuLetter';

const props = defineProps<{
    letter: KiokuLetter;
}>();

const completing = ref(false);

const character = computed(() =>
    kiokuLetterCharacterMeta(props.letter.character_variant),
);

const isCompleted = computed(() => props.letter.completed_at !== null);

const allJudged = computed(
    () =>
        props.letter.items.length > 0 &&
        props.letter.verdict_counts.judged === props.letter.items.length,
);

function requestComplete(): void {
    if (completing.value || isCompleted.value || !allJudged.value) {
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
    <section
        class="rounded-[20px] border border-os-line bg-os-kioku-paper shadow-[0_8px_28px_rgba(43,41,36,0.1)]"
    >
        <header
            class="space-y-2 border-b border-(--letter-accent-soft) px-5 py-4 sm:px-6"
        >
            <div
                class="flex items-center gap-1.5 text-[11.5px] font-bold tracking-wide text-(--letter-accent)"
            >
                <Mail :size="13" />
                今週のキオク便り
            </div>
            <h1 class="text-lg font-bold text-os-ink">
                {{ letter.week_start }} 〜 {{ letter.week_end }} の記憶から
            </h1>
            <p
                v-if="letter.intro"
                class="text-[13.5px] leading-relaxed text-os-ink"
            >
                {{ letter.intro }}
            </p>
        </header>

        <div class="space-y-5 px-5 py-4 sm:px-6">
            <!-- An empty letter is a correct result; failed/generating must
                 never be presented as empty. -->
            <p
                v-if="letter.status === 'failed'"
                class="py-6 text-center text-[13.5px] leading-relaxed text-[#C05A48]"
            >
                この手紙の生成に失敗しました。今週の手紙は作られていません。
            </p>
            <p
                v-else-if="letter.status === 'generating'"
                class="py-6 text-center text-[13.5px] leading-relaxed text-os-sub"
            >
                手紙を生成しています…
            </p>
            <p
                v-else-if="letter.items.length === 0"
                class="py-6 text-center text-[13.5px] leading-relaxed text-os-sub"
            >
                {{ KIOKU_LETTER_EMPTY_MESSAGE }}
            </p>

            <article
                v-for="item in letter.items"
                :key="item.id"
                class="space-y-2.5 border-b border-os-line pb-5 last:border-0 last:pb-0"
            >
                <h2 class="flex gap-2 text-[15px] font-bold text-os-ink">
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

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <Link
                        :href="showMemory.url(item.memory_id)"
                        class="inline-flex items-center gap-1 text-[12.5px] font-bold text-(--letter-accent) underline-offset-2 hover:underline"
                    >
                        元の記憶を開く（{{ item.title }}）
                        <ArrowUpRight :size="12" />
                    </Link>
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
                    :disabled="isCompleted"
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
                    v-if="!isCompleted"
                    type="button"
                    class="h-10 rounded-xl bg-(--letter-accent) text-[13px] font-bold text-white hover:bg-(--letter-accent-deep) disabled:opacity-40"
                    :disabled="!allJudged || completing"
                    @click="requestComplete"
                >
                    評価を完了して記録する
                </Button>
            </div>
        </div>

        <footer
            class="border-t border-(--letter-accent-soft) px-5 py-3.5 text-right sm:px-6"
        >
            <p
                class="text-[12.5px] font-bold tracking-wide text-(--letter-accent)"
            >
                {{ character.signature }}
            </p>
        </footer>
    </section>
</template>
