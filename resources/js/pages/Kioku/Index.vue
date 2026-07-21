<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Brain, Search } from '@lucide/vue';
import { computed } from 'vue';
import KiokuLetterPreview from '@/components/kioku/KiokuLetterPreview.vue';
import KiokuQuickCapture from '@/components/kioku/KiokuQuickCapture.vue';
import MemoryCard from '@/components/kioku/MemoryCard.vue';
import { Button } from '@/components/ui/button';
import { useKiokuStatusPoll } from '@/composables/useKiokuStatusPoll';
import { index as memoriesIndex } from '@/routes/kioku/memories';
import type { KiokuMemory } from '@/types/kioku';
import type {
    KiokuLetterScheduleSummary,
    KiokuLetterSummary,
} from '@/types/kiokuLetter';

interface Props {
    memories: KiokuMemory[];
    transcriptionEnabled: boolean;
    letters: KiokuLetterSummary[];
    letterSchedule?: KiokuLetterScheduleSummary | null;
}

const props = defineProps<Props>();

/**
 * Voice memories waiting on an unconfigured transcription provider stay
 * 'captured' indefinitely — polling them would only end in a false timeout.
 */
const pollableMemories = computed(() =>
    props.memories.filter(
        (memory) =>
            props.transcriptionEnabled ||
            memory.source_type !== 'voice' ||
            memory.transcription_status !== 'pending',
    ),
);

const { timedOut, timeoutMessage } = useKiokuStatusPoll(
    () => pollableMemories.value,
);

const serverCaptureIds = computed(
    () =>
        new Set(
            props.memories
                .map((memory) => memory.client_capture_id)
                .filter((id): id is string => id !== null),
        ),
);

function manualReload(): void {
    router.reload({
        only: ['memories', 'letters', 'letterSchedule'],
        preserveUrl: true,
    });
}

defineOptions({
    layout: {
        title: 'キオク',
        subtitle: '考えをそのまま残す',
    },
});
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-6 px-0 sm:px-1">
        <Head title="キオク" />

        <header class="space-y-2">
            <h2 class="font-serif text-2xl tracking-[0.08em] text-os-ink md:text-3xl">
                キオク
            </h2>
            <p class="max-w-xl text-[13.5px] leading-relaxed text-os-sub">
                考えをそのまま残す。<br class="sm:hidden" />
                AIが整理し、必要なときに思い出せる形にします。
            </p>
        </header>

        <div
            v-if="timedOut"
            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-os-line bg-os-kioku-paper px-4 py-3 text-[13px] text-os-sub shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
            role="status"
        >
            <p>{{ timeoutMessage }}</p>
            <Button
                type="button"
                variant="outline"
                class="h-9 rounded-xl border-os-kioku/30 text-os-kioku"
                @click="manualReload"
            >
                更新する
            </Button>
        </div>

        <KiokuQuickCapture
            :server-capture-ids="serverCaptureIds"
            @synced="manualReload"
        />

        <div class="grid gap-5 lg:grid-cols-2 lg:items-start">
            <KiokuLetterPreview
                :letters="letters"
                :letter-schedule="letterSchedule ?? null"
                :show-all-link="true"
                :compact-past-limit="3"
            />

            <section class="space-y-3">
                <div
                    class="flex flex-wrap items-end justify-between gap-2 px-0.5"
                >
                    <h3
                        class="text-[12px] font-bold tracking-wide text-os-kioku"
                    >
                        最近残したキオク
                    </h3>
                </div>

                <div
                    v-if="memories.length === 0"
                    class="rounded-2xl border border-os-line bg-os-kioku-paper p-8 text-center shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
                >
                    <Brain :size="26" class="mx-auto mb-2.5 text-os-faint" />
                    <p class="text-[13px] font-medium text-os-ink">
                        まだキオクがありません。
                    </p>
                    <p class="mt-1.5 text-[12.5px] leading-relaxed text-os-sub">
                        上の入力欄から、今考えていることをそのまま残してみましょう。
                    </p>
                </div>

                <MemoryCard
                    v-for="memory in memories"
                    :key="memory.id"
                    :memory="memory"
                    :transcription-enabled="transcriptionEnabled"
                />

                <div class="pt-1">
                    <Link
                        :href="memoriesIndex()"
                        class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-os-kioku/30 bg-os-kioku-paper text-[13.5px] font-bold text-os-kioku transition-colors hover:bg-os-kioku-soft sm:w-auto sm:px-5"
                    >
                        <Search :size="15" />
                        すべてのキオクを探す
                    </Link>
                </div>
            </section>
        </div>
    </div>
</template>
