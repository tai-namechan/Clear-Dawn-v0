<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import RoutinesHubTabs from '@/components/routine/RoutinesHubTabs.vue';
import {
    routineItemCategoryLabels,
    trackingTypeLabels,
} from '@/lib/routineConstants';
import type { RoutineItem } from '@/types/routine';

interface Props {
    routineItem: RoutineItem;
}

defineProps<Props>();
</script>

<template>
    <Head :title="routineItem.name" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
            <Link
                href="/routine-items"
                class="inline-flex items-center gap-2 font-sans text-sm text-cd-ink-muted transition-colors hover:text-cd-ink"
            >
                <ArrowLeft :size="16" :stroke-width="1.6" />
                実施項目一覧
            </Link>

            <PageTitleOrnament
                :title="routineItem.name"
                :subtitle="routineItemCategoryLabels[routineItem.category]"
                align="left"
            />

            <RoutinesHubTabs />

            <section
                class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface px-5 py-5"
            >
                <dl class="grid gap-4 font-sans text-sm">
                    <div>
                        <dt class="text-xs text-cd-ink-muted">記録形式</dt>
                        <dd class="mt-1 text-cd-ink">
                            {{ trackingTypeLabels[routineItem.tracking_type] }}
                        </dd>
                    </div>
                    <div v-if="routineItem.default_load_unit">
                        <dt class="text-xs text-cd-ink-muted">デフォルト負荷単位</dt>
                        <dd class="mt-1 text-cd-ink">
                            {{ routineItem.default_load_unit }}
                        </dd>
                    </div>
                    <div v-if="routineItem.default_amount_unit">
                        <dt class="text-xs text-cd-ink-muted">デフォルト量単位</dt>
                        <dd class="mt-1 text-cd-ink">
                            {{ routineItem.default_amount_unit }}
                        </dd>
                    </div>
                    <div v-if="routineItem.note">
                        <dt class="text-xs text-cd-ink-muted">メモ</dt>
                        <dd class="mt-1 text-cd-ink">{{ routineItem.note }}</dd>
                    </div>
                </dl>
            </section>

            <section
                aria-label="利用状況"
                class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface px-5 py-5"
            >
                <h2
                    class="font-serif text-base tracking-[0.12em] text-cd-ink"
                >
                    利用状況
                </h2>
                <p class="mt-3 font-sans text-sm text-cd-ink-muted">
                    この実施項目を含むルーティン・実行プランの一覧は準備中です。
                </p>
            </section>

            <section
                aria-label="実行履歴"
                class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface px-5 py-5"
            >
                <h2
                    class="font-serif text-base tracking-[0.12em] text-cd-ink"
                >
                    実行履歴
                </h2>
                <p class="mt-3 font-sans text-sm text-cd-ink-muted">
                    この実施項目の過去の記録・実績は準備中です。
                </p>
            </section>
        </div>
    </div>
</template>
