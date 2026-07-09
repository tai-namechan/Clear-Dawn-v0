<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import RoutinesHubTabs from '@/components/routine/RoutinesHubTabs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { activityLogEventTypeLabels } from '@/lib/routineConstants';
import type {
    ActivityLog,
    ActivityLogEventType,
    Paginated,
} from '@/types/routine';

interface Filters {
    event_type: string | null;
    from: string | null;
    to: string | null;
}

interface Props {
    history: Paginated<ActivityLog>;
    filters: Filters;
}

const props = defineProps<Props>();

const ALL_EVENTS = '__all__';

const localEventType = computed({
    get: () => props.filters.event_type ?? ALL_EVENTS,
    set: (value: string) =>
        applyFilters({
            event_type: value === ALL_EVENTS ? null : value,
        }),
});

const localFrom = computed({
    get: () => props.filters.from ?? '',
    set: () => {},
});

const localTo = computed({
    get: () => props.filters.to ?? '',
    set: () => {},
});

function applyFilters(overrides: Partial<Filters & { page?: number }>): void {
    router.get(
        '/history',
        {
            event_type: props.filters.event_type,
            from: props.filters.from,
            to: props.filters.to,
            ...overrides,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function submitDateFilter(from: string, to: string): void {
    applyFilters({
        from: from || null,
        to: to || null,
    });
}

function onFromDateChange(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    submitDateFilter(value, localTo.value);
}

function onToDateChange(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    submitDateFilter(localFrom.value, value);
}

function formatOccurredAt(iso: string): string {
    return new Date(iso).toLocaleString('ja-JP', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function eventDescription(log: ActivityLog): string {
    const summary = log.subject_summary;

    if (
        log.event_type === 'matrix_item_completed' &&
        summary?.type === 'matrix_cell_item'
    ) {
        return `「${summary.title}」を完了`;
    }

    if (
        log.event_type === 'matrix_item_reopened' &&
        summary?.type === 'matrix_cell_item'
    ) {
        return `「${summary.title}」を再開`;
    }

    if (log.event_type === 'routine_session_completed') {
        const title =
            summary?.type === 'routine_session' ? summary.plan_title : null;

        return title
            ? `ルーティン実行「${title}」を完了`
            : 'ルーティン実行を完了';
    }

    return activityLogEventTypeLabels[log.event_type];
}

const eventTypeOptions = Object.entries(activityLogEventTypeLabels) as Array<
    [ActivityLogEventType, string]
>;
</script>

<template>
    <Head title="実行履歴" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <PageTitleOrnament
                    title="履歴"
                    subtitle="完了したルーティンとマトリクスの活動を振り返ります。"
                    align="left"
                />
                <div class="mt-5">
                    <RoutinesHubTabs />
                </div>
            </PageSectionCard>

            <PageSectionCard aria-label="フィルター" padding="sm">
                <div class="grid gap-3 sm:grid-cols-3">
                    <Select v-model="localEventType">
                        <SelectTrigger>
                            <SelectValue placeholder="種別" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL_EVENTS">
                                すべて
                            </SelectItem>
                            <SelectItem
                                v-for="[value, label] in eventTypeOptions"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Input
                        :model-value="localFrom"
                        type="date"
                        placeholder="開始日"
                        @change="onFromDateChange"
                    />

                    <Input
                        :model-value="localTo"
                        type="date"
                        placeholder="終了日"
                        @change="onToDateChange"
                    />
                </div>
            </PageSectionCard>

            <PageSectionCard padding="none" aria-label="履歴タイムライン">
                <ul
                    v-if="history.data.length > 0"
                    class="relative flex flex-col"
                >
                    <li
                        v-for="log in history.data"
                        :key="log.id"
                        class="relative border-b border-cd-line px-5 py-4 pl-10 last:border-b-0"
                    >
                        <span
                            aria-hidden="true"
                            class="absolute top-5 left-4 size-2 rounded-full bg-primary"
                        />
                        <span
                            aria-hidden="true"
                            class="absolute top-6 left-[1.125rem] h-full w-px bg-cd-line last:hidden"
                        />

                        <p class="font-sans text-xs text-cd-ink-muted">
                            {{ formatOccurredAt(log.occurred_at) }}
                        </p>
                        <p class="mt-1 font-sans text-base font-semibold text-cd-ink">
                            {{ eventDescription(log) }}
                        </p>
                        <span
                            class="mt-1 inline-block font-sans text-xs text-cd-ink-muted"
                        >
                            {{ activityLogEventTypeLabels[log.event_type] }}
                        </span>
                    </li>
                </ul>

                <p
                    v-else
                    class="px-5 py-12 text-center font-sans text-sm text-cd-ink-muted"
                >
                    履歴がありません。
                </p>
            </PageSectionCard>

            <div
                v-if="history.meta.last_page > 1"
                class="flex items-center justify-center gap-3"
            >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="!history.links.prev"
                    @click="
                        applyFilters({
                            page: history.meta.current_page - 1,
                        })
                    "
                >
                    前へ
                </Button>
                <span class="font-sans text-sm text-cd-ink-muted">
                    {{ history.meta.current_page }} /
                    {{ history.meta.last_page }}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="!history.links.next"
                    @click="
                        applyFilters({
                            page: history.meta.current_page + 1,
                        })
                    "
                >
                    次へ
                </Button>
            </div>
        </div>
    </div>
</template>
