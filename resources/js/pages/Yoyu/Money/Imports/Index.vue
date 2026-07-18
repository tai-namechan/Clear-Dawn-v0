<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import MoneySubnav from '@/components/yoyu-money/MoneySubnav.vue';
import type { MoneyImportRow } from '@/lib/yoyuMoney/types';

interface Props {
    imports: MoneyImportRow[];
}

defineProps<Props>();

function rollback(importRow: MoneyImportRow): void {
    if (!confirm(`「${importRow.source_filename ?? importRow.id}」の取込を取り消しますか？`)) {
        return;
    }

    router.post(`/yoyu/money/imports/${importRow.id}/rollback`, {}, {
        preserveScroll: true,
    });
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

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: 'CSV取込',
    },
});
</script>

<template>
    <div class="mx-auto max-w-[720px] space-y-4">
        <Head title="CSV取込 — お金の余裕" />

        <MoneySubnav active="imports" />

        <div class="flex justify-end">
            <Button as-child size="sm" class="rounded-full">
                <Link href="/yoyu/money/imports/create">新規取込</Link>
            </Button>
        </div>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">取込履歴</h2>
            <p v-if="imports.length === 0" class="text-[13px] text-os-sub">
                取込履歴はまだありません。
            </p>
            <ul v-else class="divide-y divide-os-line">
                <li
                    v-for="item in imports"
                    :key="item.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                >
                    <div>
                        <p class="font-semibold text-os-ink">
                            {{ item.source_filename || '(ファイル名なし)' }}
                        </p>
                        <p class="text-[12px] text-os-sub">
                            {{ statusLabel(item.status) }} ·
                            {{ item.row_count ?? 0 }} 行 ·
                            {{ item.created_at ?? '—' }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            v-if="
                                item.status === 'uploaded' ||
                                item.status === 'mapped' ||
                                item.status === 'previewed' ||
                                item.status === 'failed'
                            "
                            as-child
                            size="sm"
                            variant="outline"
                        >
                            <Link
                                :href="`/yoyu/money/imports/create?import_id=${item.id}`"
                            >
                                続行
                            </Link>
                        </Button>
                        <Button
                            v-if="item.status === 'completed'"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="text-[#C05A48]"
                            @click="rollback(item)"
                        >
                            取消
                        </Button>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
