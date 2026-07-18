<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { show } from '@/routes/programs';
import type { RoadmapData } from '@/types/program';

interface Props {
    program: { id: string; name: string };
    roadmap: RoadmapData;
}

const props = defineProps<Props>();

const weekdayLabels = ['', '月', '火', '水', '木', '金', '土', '日'];

const phaseClasses: Record<string, string> = {
    base: 'bg-cd-moss/15 text-cd-moss',
    deload: 'bg-muted text-cd-ink-muted',
    intensify: 'bg-primary/10 text-primary',
    taper: 'bg-cd-moss/10 text-cd-moss',
    test: 'bg-destructive/10 text-destructive',
};

const selectedWeekNumber = ref(
    props.roadmap.version.current_week_number ??
        props.roadmap.weeks[0]?.week_number ??
        1,
);

const selectedWeek = computed(() =>
    props.roadmap.weeks.find(
        (week) => week.week_number === selectedWeekNumber.value,
    ),
);
</script>

<template>
    <Head :title="`${program.name} ロードマップ`" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <div class="flex items-start justify-between gap-4">
                    <PageTitleOrnament
                        title="ロードマップ"
                        :subtitle="program.name"
                        align="left"
                    />
                    <Link
                        :href="show(program.id)"
                        class="mt-2 flex shrink-0 items-center gap-2 rounded-full border border-cd-line px-3.5 py-1.5 font-sans text-sm text-cd-ink-muted transition-colors hover:border-primary/30 hover:bg-primary-hover hover:text-primary"
                    >
                        <ArrowLeft
                            :size="16"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                        プログラム詳細へ
                    </Link>
                </div>

                <div class="mt-4 flex flex-wrap gap-2" aria-label="フェーズ帯">
                    <span
                        v-for="phase in roadmap.phases"
                        :key="phase.id"
                        class="inline-flex items-center gap-1 rounded-full px-3 py-1 font-sans text-xs"
                        :class="phaseClasses[phase.intent] ?? 'bg-muted'"
                    >
                        {{ phase.name }} W{{ phase.week_numbers[0] }}〜W{{
                            phase.week_numbers[phase.week_numbers.length - 1]
                        }}
                    </span>
                </div>
            </PageSectionCard>

            <PageSectionCard padding="none" aria-label="週タブ">
                <div
                    class="flex gap-1 overflow-x-auto border-b border-cd-line px-3 py-2"
                    role="tablist"
                >
                    <button
                        v-for="week in roadmap.weeks"
                        :key="week.id"
                        type="button"
                        role="tab"
                        :aria-selected="week.week_number === selectedWeekNumber"
                        class="shrink-0 rounded-full px-3 py-1 font-sans text-sm transition-colors"
                        :class="
                            week.week_number === selectedWeekNumber
                                ? 'bg-primary text-primary-foreground'
                                : week.week_number ===
                                    roadmap.version.current_week_number
                                  ? 'bg-primary/10 text-primary'
                                  : 'text-cd-ink-muted hover:bg-muted'
                        "
                        @click="selectedWeekNumber = week.week_number"
                    >
                        W{{ week.week_number }}
                    </button>
                </div>

                <div v-if="selectedWeek" class="px-5 py-4">
                    <div
                        class="flex flex-wrap items-center gap-x-4 gap-y-1 font-sans text-sm"
                    >
                        <span class="font-semibold text-cd-ink">
                            W{{ selectedWeek.week_number }}
                            {{ selectedWeek.intent }}
                        </span>
                        <span class="text-cd-ink-muted">
                            {{ selectedWeek.starts_on }} 開始
                        </span>
                        <span
                            v-if="
                                selectedWeek.week_number ===
                                roadmap.version.current_week_number
                            "
                            class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary"
                        >
                            今週
                        </span>
                    </div>

                    <div
                        v-if="selectedWeek.prescriptions.length > 0"
                        class="mt-3 overflow-x-auto"
                    >
                        <table class="w-full font-sans text-sm">
                            <thead>
                                <tr
                                    class="border-b border-cd-line text-left text-xs text-cd-ink-muted"
                                >
                                    <th class="py-2 pr-3 font-normal">種目</th>
                                    <th class="py-2 pr-3 font-normal">重量</th>
                                    <th class="py-2 pr-3 font-normal">
                                        セット×レップ
                                    </th>
                                    <th class="py-2 font-normal">RPE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="prescription in selectedWeek.prescriptions"
                                    :key="prescription.id"
                                    class="border-b border-cd-line/60 last:border-b-0"
                                >
                                    <td class="py-2 pr-3 text-cd-ink">
                                        {{ prescription.item_name }}
                                        <span class="text-xs text-cd-ink-muted">
                                            ({{ prescription.day_code }})
                                        </span>
                                    </td>
                                    <td class="py-2 pr-3">
                                        <template v-if="prescription.is_test">
                                            <span
                                                class="rounded-full bg-destructive/10 px-2 py-0.5 text-xs text-destructive"
                                            >
                                                1RM測定
                                            </span>
                                        </template>
                                        <template
                                            v-else-if="
                                                prescription.display_load !==
                                                null
                                            "
                                        >
                                            <span
                                                class="font-semibold text-cd-ink"
                                            >
                                                {{ prescription.display_load
                                                }}{{
                                                    prescription.load_unit ??
                                                    'kg'
                                                }}
                                            </span>
                                            <span
                                                class="ml-1 text-xs text-cd-ink-muted"
                                            >
                                                ({{
                                                    (
                                                        Number(
                                                            prescription.percent_of_reference,
                                                        ) * 100
                                                    ).toFixed(1)
                                                }}%)
                                            </span>
                                        </template>
                                        <template
                                            v-else-if="
                                                prescription.percent_of_reference !==
                                                null
                                            "
                                        >
                                            1RM比
                                            {{
                                                (
                                                    Number(
                                                        prescription.percent_of_reference,
                                                    ) * 100
                                                ).toFixed(1)
                                            }}%
                                        </template>
                                        <template v-else>—</template>
                                    </td>
                                    <td class="py-2 pr-3 text-cd-ink">
                                        <template
                                            v-if="
                                                prescription.sets !== null &&
                                                prescription.reps !== null
                                            "
                                        >
                                            {{ prescription.sets }}×{{
                                                prescription.reps
                                            }}
                                        </template>
                                        <template v-else>—</template>
                                    </td>
                                    <td class="py-2 text-cd-ink">
                                        {{
                                            prescription.rpe_target !== null
                                                ? Number(
                                                      prescription.rpe_target,
                                                  )
                                                : '—'
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="mt-2 font-sans text-xs text-cd-ink-muted">
                            重量は現在1RM × 週次比率（1.25kg丸め）。1RM
                            未登録の種目は比率のみ表示されます。
                        </p>
                    </div>
                </div>
            </PageSectionCard>

            <PageSectionCard padding="none" aria-label="週のDAY構成">
                <h2
                    class="border-b border-cd-line px-5 py-4 font-sans text-base font-semibold text-cd-ink"
                >
                    週のDAY構成
                </h2>
                <ul class="divide-y divide-cd-line">
                    <li
                        v-for="day in roadmap.day_templates"
                        :key="day.id"
                        class="px-5 py-3"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="font-sans text-sm font-semibold text-cd-ink"
                            >
                                {{ day.code }} {{ day.name }}
                            </span>
                            <span
                                v-if="day.fixed_weekday"
                                class="font-sans text-xs text-cd-ink-muted"
                            >
                                {{ weekdayLabels[day.fixed_weekday] }}曜
                            </span>
                            <span
                                v-if="day.estimated_minutes_min !== null"
                                class="ml-auto font-sans text-xs text-cd-ink-muted"
                            >
                                {{ day.estimated_minutes_min }}〜{{
                                    day.estimated_minutes_max
                                }}分
                            </span>
                        </div>
                        <p class="mt-1 font-sans text-xs text-cd-ink-muted">
                            {{ day.step_names.join(' → ') }}
                        </p>
                    </li>
                </ul>
            </PageSectionCard>
        </div>
    </div>
</template>
