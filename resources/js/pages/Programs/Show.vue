<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarRange } from '@lucide/vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { show as showGoal } from '@/routes/goals';
import { index, roadmap } from '@/routes/programs';
import type { ProgramDetail, ProgramStepItemDetail } from '@/types/program';

interface Props {
    program: ProgramDetail;
}

defineProps<Props>();

const weekdayLabels = ['', '月', '火', '水', '木', '金', '土', '日'];

const tierLabels: Record<string, string> = {
    never_cut: '絶対に削らない',
    keep: '守る',
    cut_ok: '削ってよい',
};

const tierClasses: Record<string, string> = {
    never_cut: 'bg-destructive/10 text-destructive',
    keep: 'bg-cd-moss/15 text-cd-moss',
    cut_ok: 'bg-muted text-cd-ink-muted',
};

const stepKindLabels: Record<string, string> = {
    preparation: 'プレップ',
    movement: 'ムーブメント',
    power: 'パワー',
    throwing: '投球',
    strength: 'ストレングス',
    accessory: '補助',
    arm_care: 'アームケア',
    conditioning: 'コンディショニング',
    cooldown: 'クールダウン',
};

function prescriptionLine(item: ProgramStepItemDetail): string {
    const parts: string[] = [];

    if (item.sets !== null && item.reps !== null) {
        parts.push(`${item.sets}セット × ${item.reps}回`);
    } else if (item.sets !== null && item.amount_value !== null) {
        parts.push(
            `${item.sets}セット × ${Number(item.amount_value)}${item.amount_unit ?? ''}`,
        );
    } else if (item.amount_value !== null) {
        parts.push(`${Number(item.amount_value)}${item.amount_unit ?? ''}`);
    }

    if (item.percent_of_reference !== null) {
        parts.push(
            `1RM比 ${(Number(item.percent_of_reference) * 100).toFixed(1)}%`,
        );
    } else if (item.fixed_load !== null) {
        parts.push(`${Number(item.fixed_load)}${item.load_unit ?? 'kg'}`);
    }

    if (item.rpe_target !== null) {
        parts.push(`RPE ${Number(item.rpe_target)}`);
    }

    return parts.join(' / ');
}
</script>

<template>
    <Head :title="program.name" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <div class="flex items-start justify-between gap-4">
                    <PageTitleOrnament
                        :title="program.name"
                        :subtitle="program.purpose ?? undefined"
                        align="left"
                    />
                    <Link
                        :href="index()"
                        class="mt-2 flex shrink-0 items-center gap-2 rounded-full border border-cd-line px-3.5 py-1.5 font-sans text-sm text-cd-ink-muted transition-colors hover:border-primary/30 hover:bg-primary-hover hover:text-primary"
                    >
                        <ArrowLeft
                            :size="16"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                        一覧へ戻る
                    </Link>
                </div>

                <p
                    v-if="program.design_philosophy"
                    class="mt-3 font-sans text-sm text-cd-ink-muted"
                >
                    {{ program.design_philosophy }}
                </p>

                <div
                    class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 font-sans text-sm"
                >
                    <Link
                        v-if="program.goal"
                        :href="showGoal(program.goal.id)"
                        class="text-primary hover:underline"
                    >
                        目標: {{ program.goal.name }}
                    </Link>
                    <Link
                        :href="roadmap(program.id)"
                        class="inline-flex items-center gap-1 text-primary hover:underline"
                    >
                        <CalendarRange
                            :size="14"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                        ロードマップを見る
                    </Link>
                </div>
            </PageSectionCard>

            <PageSectionCard
                v-if="program.active_version"
                aria-label="フェーズ構成"
            >
                <h2 class="mb-3 font-sans text-base font-semibold text-cd-ink">
                    フェーズ構成（v{{ program.active_version.version_number }} ·
                    {{ program.active_version.starts_on }} 〜
                    {{ program.active_version.ends_on }}）
                </h2>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="phase in program.active_version.phases"
                        :key="phase.id"
                        class="rounded-lg border border-cd-line px-4 py-2.5"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="font-sans text-sm font-semibold text-cd-ink"
                            >
                                {{ phase.name }}
                            </span>
                            <span class="font-sans text-xs text-cd-ink-muted">
                                W{{ phase.week_numbers[0] }}〜W{{
                                    phase.week_numbers[
                                        phase.week_numbers.length - 1
                                    ]
                                }}
                            </span>
                        </div>
                        <p
                            v-if="phase.progression_conditions"
                            class="mt-1 font-sans text-xs text-cd-ink-muted"
                        >
                            {{ phase.progression_conditions }}
                        </p>
                    </li>
                </ul>
            </PageSectionCard>

            <PageSectionCard
                v-for="day in program.active_version?.day_templates ?? []"
                :key="day.id"
                padding="none"
                :aria-label="day.name"
            >
                <div
                    class="flex flex-wrap items-center gap-2 border-b border-cd-line px-5 py-4"
                >
                    <h2 class="font-sans text-base font-semibold text-cd-ink">
                        {{ day.code }} {{ day.name }}
                    </h2>
                    <span
                        v-if="day.fixed_weekday"
                        class="font-sans text-sm text-cd-ink-muted"
                    >
                        {{ weekdayLabels[day.fixed_weekday] }}曜
                    </span>
                    <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 font-sans text-xs"
                        :class="tierClasses[day.priority_tier]"
                    >
                        {{ tierLabels[day.priority_tier] }}
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

                <p
                    v-if="day.note"
                    class="border-b border-cd-line bg-muted/40 px-5 py-2.5 font-sans text-xs text-cd-ink-muted"
                >
                    {{ day.note }}
                </p>

                <div
                    v-if="day.choice_group"
                    class="border-b border-cd-line px-5 py-3"
                >
                    <p class="font-sans text-sm font-semibold text-cd-ink">
                        {{ day.choice_group.name }}
                    </p>
                    <p
                        v-if="day.choice_group.selection_hint"
                        class="mt-0.5 font-sans text-xs text-cd-ink-muted"
                    >
                        {{ day.choice_group.selection_hint }}
                    </p>
                    <ul
                        class="mt-2 flex flex-wrap gap-2 font-sans text-xs text-cd-ink"
                    >
                        <li
                            v-for="option in day.choice_group.options"
                            :key="option.id"
                            class="rounded-full border border-cd-line px-3 py-1"
                        >
                            {{ option.label
                            }}<template v-if="option.estimated_minutes">
                                · {{ option.estimated_minutes }}分</template
                            >
                        </li>
                    </ul>
                </div>

                <div
                    v-for="step in day.steps"
                    :key="step.id"
                    class="border-b border-cd-line px-5 py-3 last:border-b-0"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 font-sans text-xs text-primary"
                        >
                            {{
                                stepKindLabels[step.step_kind] ?? step.step_kind
                            }}
                        </span>
                        <span
                            class="font-sans text-sm font-semibold text-cd-ink"
                        >
                            {{ step.name }}
                        </span>
                        <span
                            v-if="step.required_level === 'required'"
                            class="font-sans text-xs text-destructive"
                        >
                            必須
                        </span>
                    </div>
                    <p
                        v-if="step.note"
                        class="mt-1 font-sans text-xs text-cd-ink-muted"
                    >
                        {{ step.note }}
                    </p>
                    <ul class="mt-2 flex flex-col gap-1.5">
                        <li
                            v-for="item in step.items"
                            :key="item.id"
                            class="font-sans text-sm"
                        >
                            <span class="text-cd-ink">{{ item.name }}</span>
                            <span class="ml-2 text-cd-ink-muted">
                                {{ prescriptionLine(item) }}
                            </span>
                            <p
                                v-if="item.cues"
                                class="text-xs text-cd-ink-muted"
                            >
                                💡 {{ item.cues }}
                            </p>
                            <p
                                v-if="item.abort_condition"
                                class="text-xs text-destructive"
                            >
                                ⚠ {{ item.abort_condition }}
                            </p>
                        </li>
                    </ul>
                </div>
            </PageSectionCard>

            <PageSectionCard
                v-if="(program.active_version?.constraints ?? []).length > 0"
                padding="none"
                aria-label="制約・配置原則"
            >
                <h2
                    class="border-b border-cd-line px-5 py-4 font-sans text-base font-semibold text-cd-ink"
                >
                    制約・配置原則
                </h2>
                <ul class="divide-y divide-cd-line">
                    <li
                        v-for="constraint in program.active_version
                            ?.constraints"
                        :key="constraint.id"
                        class="px-5 py-3 font-sans text-sm text-cd-ink"
                    >
                        {{ constraint.description }}
                    </li>
                </ul>
            </PageSectionCard>

            <PageSectionCard
                v-if="(program.versions ?? []).length > 1"
                padding="none"
                aria-label="版履歴"
            >
                <h2
                    class="border-b border-cd-line px-5 py-4 font-sans text-base font-semibold text-cd-ink"
                >
                    版履歴
                </h2>
                <ul class="divide-y divide-cd-line">
                    <li
                        v-for="version in program.versions"
                        :key="version.id"
                        class="px-5 py-3 font-sans text-sm"
                    >
                        <span class="font-semibold text-cd-ink">
                            v{{ version.version_number }}
                        </span>
                        <span class="ml-2 text-cd-ink-muted">
                            {{ version.starts_on }} 〜 {{ version.ends_on }}
                        </span>
                        <p
                            v-if="version.change_summary"
                            class="mt-0.5 text-xs text-cd-ink-muted"
                        >
                            {{ version.change_summary }}
                        </p>
                    </li>
                </ul>
            </PageSectionCard>
        </div>
    </div>
</template>
