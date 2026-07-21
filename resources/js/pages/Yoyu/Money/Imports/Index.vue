<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import MoneyEmptyState from '@/components/yoyu-money/MoneyEmptyState.vue';
import MoneyPageShell from '@/components/yoyu-money/MoneyPageShell.vue';
import { moneyLedgerTabs } from '@/lib/yoyuMoney/navigation';
import type { MoneyImportRow } from '@/lib/yoyuMoney/types';

interface Props {
    imports: MoneyImportRow[];
}

defineProps<Props>();

function rollback(importRow: MoneyImportRow): void {
    if (!confirm(`「${importRow.source_filename ?? importRow.id}」の取込を取り消しますか？`)) {
        return;
    }

    router.post(
        `/yoyu/money/imports/${importRow.id}/rollback`,
        {},
        { preserveScroll: true },
    );
}

function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        uploaded: 'アップロード済',
        mapped: 'マッピング済',
        previewed: 'プレビュー済',
        processing: '処理中',
        completed: '完了',
        failed: '失敗',
        rolled_back: '取消済',
    };

    return labels[status] ?? status;
}

function canContinue(status: string): boolean {
    return ['uploaded', 'mapped', 'previewed', 'failed'].includes(status);
}

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: 'CSV取込',
    },
});
</script>

<template>
    <MoneyPageShell
        title="取込履歴"
        :section-tabs="moneyLedgerTabs"
        section-active="imports"
        section-label="明細"
        primary-active="ledger"
    >
        <template #actions>
            <Button as-child class="rounded-lg">
                <Link href="/yoyu/money/imports/create">＋新規取込</Link>
            </Button>
        </template>

        <MoneyEmptyState
            v-if="imports.length === 0"
            title="取込履歴がありません"
            description="銀行やカード会社のCSVを取り込むと、取引を一括で登録できます。"
            action-label="CSVを取り込む"
            action-href="/yoyu/money/imports/create"
        />

        <section
            v-else
            class="overflow-hidden rounded-2xl border border-os-line bg-white shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <!-- Desktop table -->
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-left text-[13px]">
                    <thead class="border-b border-os-line bg-os-yoyu-bg/80 text-os-sub">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">ファイル名</th>
                            <th class="px-4 py-2.5 font-semibold">状態</th>
                            <th class="px-4 py-2.5 font-semibold">件数</th>
                            <th class="px-4 py-2.5 font-semibold">日時</th>
                            <th class="px-4 py-2.5 font-semibold">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in imports"
                            :key="item.id"
                            class="border-b border-os-line/80"
                        >
                            <td class="px-4 py-3 font-semibold text-os-ink">
                                {{ item.source_filename || '(ファイル名なし)' }}
                            </td>
                            <td class="px-4 py-3 text-os-sub">{{ statusLabel(item.status) }}</td>
                            <td class="px-4 py-3 tabular-nums text-os-sub">
                                {{ item.row_count ?? 0 }} 行
                            </td>
                            <td class="px-4 py-3 text-os-faint">{{ item.created_at ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <Button
                                        v-if="canContinue(item.status)"
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link :href="`/yoyu/money/imports/create?import_id=${item.id}`">
                                            続行
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="item.status === 'completed'"
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        class="text-[#8A5A3B]"
                                        @click="rollback(item)"
                                    >
                                        取消
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <ul class="divide-y divide-os-line md:hidden">
                <li
                    v-for="item in imports"
                    :key="`m-${item.id}`"
                    class="p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-os-ink">
                                {{ item.source_filename || '(ファイル名なし)' }}
                            </p>
                            <p class="text-[12px] text-os-sub">
                                {{ statusLabel(item.status) }} · {{ item.row_count ?? 0 }} 行 ·
                                {{ item.created_at ?? '—' }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-2 flex gap-2">
                        <Button
                            v-if="canContinue(item.status)"
                            as-child
                            size="sm"
                            variant="outline"
                        >
                            <Link :href="`/yoyu/money/imports/create?import_id=${item.id}`">
                                続行
                            </Link>
                        </Button>
                        <Button
                            v-if="item.status === 'completed'"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="text-[#8A5A3B]"
                            @click="rollback(item)"
                        >
                            取消
                        </Button>
                    </div>
                </li>
            </ul>
        </section>
    </MoneyPageShell>
</template>
