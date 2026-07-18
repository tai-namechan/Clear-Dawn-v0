<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Plus, Target } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { show, store } from '@/routes/goals';
import type { GoalSummary } from '@/types/program';

interface Props {
    goals: GoalSummary[];
}

defineProps<Props>();

const statusLabels: Record<string, string> = {
    draft: '下書き',
    active: '進行中',
    achieved: '達成',
    abandoned: '中止',
};

const statusClasses: Record<string, string> = {
    draft: 'bg-muted text-cd-ink-muted',
    active: 'bg-cd-moss/15 text-cd-moss',
    achieved: 'bg-primary/10 text-primary',
    abandoned: 'bg-muted text-cd-ink-muted line-through',
};
</script>

<template>
    <Head title="目標" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <PageTitleOrnament
                    title="目標"
                    subtitle="将来どうなっていたいかを、達成指標つきの実行可能な目標として構造化します。"
                    align="left"
                />
            </PageSectionCard>

            <PageSectionCard padding="none" aria-label="目標ツリー">
                <h2
                    class="border-b border-cd-line px-5 py-4 font-sans text-base font-semibold text-cd-ink"
                >
                    目標ツリー
                </h2>

                <p
                    v-if="goals.length === 0"
                    class="px-5 py-8 text-center font-sans text-sm text-cd-ink-muted"
                >
                    まだ目標がありません。下のフォームから最初の目標を追加しましょう。
                </p>

                <ul v-else class="divide-y divide-cd-line">
                    <li v-for="goal in goals" :key="goal.id" class="px-5 py-4">
                        <Link
                            :href="show(goal.id)"
                            class="group flex items-center gap-3"
                        >
                            <Target
                                :size="18"
                                :stroke-width="1.6"
                                class="shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span
                                class="min-w-0 truncate font-sans text-base font-semibold text-cd-ink group-hover:text-primary"
                            >
                                {{ goal.name }}
                            </span>
                            <span
                                class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs"
                                :class="statusClasses[goal.status]"
                            >
                                {{ statusLabels[goal.status] }}
                            </span>
                            <span
                                v-if="goal.deadline"
                                class="shrink-0 font-sans text-xs text-cd-ink-muted"
                            >
                                期日 {{ goal.deadline }}
                            </span>
                            <ChevronRight
                                :size="16"
                                :stroke-width="1.6"
                                class="ml-auto shrink-0 text-cd-ink-muted"
                                aria-hidden="true"
                            />
                        </Link>

                        <ul
                            v-if="goal.children && goal.children.length > 0"
                            class="mt-2 flex flex-col gap-1 border-l border-cd-line pl-6"
                        >
                            <li v-for="child in goal.children" :key="child.id">
                                <Link
                                    :href="show(child.id)"
                                    class="group flex items-center gap-2 py-1"
                                >
                                    <span
                                        class="min-w-0 truncate font-sans text-sm text-cd-ink group-hover:text-primary"
                                    >
                                        {{ child.name }}
                                    </span>
                                    <span
                                        class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs"
                                        :class="statusClasses[child.status]"
                                    >
                                        {{ statusLabels[child.status] }}
                                    </span>
                                    <span
                                        v-if="
                                            child.goal_metrics &&
                                            child.goal_metrics.length > 0
                                        "
                                        class="shrink-0 font-sans text-xs text-cd-ink-muted"
                                    >
                                        指標 {{ child.goal_metrics.length }}件
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </li>
                </ul>
            </PageSectionCard>

            <PageSectionCard aria-label="新しい目標を追加">
                <h2 class="mb-4 font-sans text-base font-semibold text-cd-ink">
                    新しい目標を追加
                </h2>
                <Form
                    v-bind="store.form()"
                    :options="{ preserveScroll: true }"
                    reset-on-success
                    class="flex flex-col gap-3"
                    v-slot="{ errors, processing }"
                >
                    <Input
                        name="name"
                        required
                        maxlength="100"
                        placeholder="目標名（例: 競技復帰）"
                    />
                    <InputError :message="errors.name" />

                    <Input
                        name="why"
                        maxlength="500"
                        placeholder="なぜ達成したいか（任意）"
                    />
                    <InputError :message="errors.why" />

                    <Input name="deadline" type="date" aria-label="期日" />
                    <InputError :message="errors.deadline" />

                    <div class="flex justify-end pt-1">
                        <Button
                            type="submit"
                            class="font-sans tracking-[0.08em]"
                            :disabled="processing"
                        >
                            <Plus :size="16" :stroke-width="1.8" />
                            追加する
                        </Button>
                    </div>
                </Form>
            </PageSectionCard>
        </div>
    </div>
</template>
