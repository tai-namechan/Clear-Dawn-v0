<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, LineChart } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { apiFetch } from '@/lib/apiFetch';
import type { DailyMetricEntry } from '@/types/training';

interface Props {
    date: string;
    metrics: DailyMetricEntry[];
}

const props = defineProps<Props>();

const values = ref<Record<string, string>>(
    Object.fromEntries(
        props.metrics.map((entry) => [
            entry.metric.key,
            entry.record?.value ?? '',
        ]),
    ),
);

const notes = ref<Record<string, string>>(
    Object.fromEntries(
        props.metrics.map((entry) => [
            entry.metric.key,
            entry.record?.note ?? '',
        ]),
    ),
);

const saving = ref(false);
const saveMessage = ref<string | null>(null);

const formattedDate = computed(() => {
    const d = new Date(`${props.date}T00:00:00`);

    return d.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'short',
    });
});

const isToday = computed(
    () => props.date === new Date().toISOString().slice(0, 10),
);

function shiftDate(days: number): void {
    const current = new Date(`${props.date}T00:00:00`);
    current.setDate(current.getDate() + days);

    router.get(
        '/records',
        { date: current.toISOString().slice(0, 10) },
        { preserveState: true, preserveScroll: true },
    );
}

function goToday(): void {
    router.get('/records', {}, { preserveState: true, preserveScroll: true });
}

async function saveAll(): Promise<void> {
    saving.value = true;
    saveMessage.value = null;

    const records = props.metrics
        .filter((entry) => values.value[entry.metric.key]?.trim())
        .map((entry) => ({
            metric_key: entry.metric.key,
            value: Number(values.value[entry.metric.key]),
            note: notes.value[entry.metric.key]?.trim() || null,
        }));

    if (records.length === 0) {
        saveMessage.value = '入力された項目がありません。';
        saving.value = false;

        return;
    }

    try {
        await apiFetch('/records/daily', {
            method: 'PUT',
            body: JSON.stringify({
                recorded_on: props.date,
                records,
            }),
        });

        saveMessage.value = '保存しました。';
        router.reload({ only: ['metrics', 'date'] });
    } catch {
        saveMessage.value = '保存に失敗しました。';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head title="コンディション管理" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
            <PageTitleOrnament
                title="コンディション管理"
                subtitle="日々のコンディション指標をまとめて記録します。"
                align="left"
            />

            <div
                class="flex items-center justify-between gap-3 rounded-2xl border border-cd-line/80 bg-white/60 px-4 py-3"
            >
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="前の日"
                    @click="shiftDate(-1)"
                >
                    <ChevronLeft :size="18" :stroke-width="1.6" />
                </Button>

                <div class="text-center">
                    <p
                        class="font-serif text-base tracking-[0.1em] text-cd-ink"
                    >
                        {{ formattedDate }}
                    </p>
                    <button
                        v-if="!isToday"
                        type="button"
                        class="mt-0.5 font-sans text-xs text-primary underline-offset-2 hover:underline"
                        @click="goToday"
                    >
                        今日に戻る
                    </button>
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="次の日"
                    @click="shiftDate(1)"
                >
                    <ChevronRight :size="18" :stroke-width="1.6" />
                </Button>
            </div>

            <section
                aria-label="日次入力"
                class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface"
            >
                <ul class="flex flex-col">
                    <li
                        v-for="entry in metrics"
                        :key="entry.metric.key"
                        class="border-b border-cd-line/60 px-5 py-4 last:border-b-0"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <label
                                        :for="`metric-${entry.metric.key}`"
                                        class="font-serif text-base tracking-[0.08em] text-cd-ink"
                                    >
                                        {{ entry.metric.label }}
                                    </label>
                                    <Link
                                        :href="`/records/${entry.metric.key}`"
                                        class="text-cd-ink-muted transition-colors hover:text-primary"
                                        :aria-label="`${entry.metric.label} の推移を見る`"
                                    >
                                        <LineChart
                                            :size="14"
                                            :stroke-width="1.6"
                                        />
                                    </Link>
                                </div>
                                <p
                                    class="mt-0.5 font-sans text-xs text-cd-ink-muted"
                                >
                                    単位: {{ entry.metric.unit }}
                                </p>
                            </div>

                            <div class="flex w-full flex-col gap-2 sm:w-48">
                                <Input
                                    :id="`metric-${entry.metric.key}`"
                                    v-model="values[entry.metric.key]"
                                    type="number"
                                    :step="
                                        entry.metric.value_type === 'decimal'
                                            ? '0.1'
                                            : '1'
                                    "
                                    :min="
                                        entry.metric.value_type === 'scale_1_5'
                                            ? 1
                                            : undefined
                                    "
                                    :max="
                                        entry.metric.value_type === 'scale_1_5'
                                            ? 5
                                            : undefined
                                    "
                                    :placeholder="entry.metric.unit"
                                />
                                <Input
                                    v-model="notes[entry.metric.key]"
                                    placeholder="メモ（任意）"
                                    class="text-sm"
                                />
                            </div>
                        </div>
                    </li>
                </ul>
            </section>

            <div class="flex items-center justify-between gap-3">
                <p
                    v-if="saveMessage"
                    class="font-sans text-sm"
                    :class="
                        saveMessage.includes('失敗')
                            ? 'text-destructive'
                            : 'text-cd-moss'
                    "
                >
                    {{ saveMessage }}
                </p>
                <span v-else />

                <Button
                    type="button"
                    class="font-sans tracking-[0.08em]"
                    :disabled="saving"
                    @click="saveAll"
                >
                    まとめて保存
                </Button>
            </div>
        </div>
    </div>
</template>
