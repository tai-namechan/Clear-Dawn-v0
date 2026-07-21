<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { Button } from '@/components/ui/button';
import MoneyPageShell from '@/components/yoyu-money/MoneyPageShell.vue';
import { moneyLedgerTabs } from '@/lib/yoyuMoney/navigation';

type AccountOption = {
    id: string;
    name: string;
    type: string;
};

type ImportDraft = {
    id: string;
    account_id: string;
    status: string;
    source_filename: string | null;
    row_count: number | null;
    mapping_config: Record<string, unknown> | null;
};

interface Props {
    accounts: AccountOption[];
    currentImport?: ImportDraft | null;
}

const props = withDefaults(defineProps<Props>(), {
    currentImport: null,
});

const uploadForm = useForm({
    account_id: props.accounts[0]?.id ?? '',
    file: null as File | null,
});

const mapping = reactive({
    date_column: String(
        props.currentImport?.mapping_config?.date_column ?? '0',
    ),
    description_column: String(
        props.currentImport?.mapping_config?.description_column ?? '1',
    ),
    amount_column: String(
        props.currentImport?.mapping_config?.amount_column ?? '2',
    ),
    debit_column: String(
        props.currentImport?.mapping_config?.debit_column ?? '',
    ),
    credit_column: String(
        props.currentImport?.mapping_config?.credit_column ?? '',
    ),
    external_id_column: String(
        props.currentImport?.mapping_config?.external_id_column ?? '',
    ),
    date_format: String(
        props.currentImport?.mapping_config?.date_format ?? 'Y-m-d',
    ),
    amount_sign: String(
        props.currentImport?.mapping_config?.amount_sign ?? 'signed',
    ),
    encoding: String(props.currentImport?.mapping_config?.encoding ?? 'UTF-8'),
    delimiter: String(props.currentImport?.mapping_config?.delimiter ?? ','),
    has_header: Boolean(
        props.currentImport?.mapping_config?.has_header ?? true,
    ),
});

const step = computed(() => {
    if (!props.currentImport) {
        return 1;
    }

    if (props.currentImport.status === 'previewed') {
        return 3;
    }

    if (
        props.currentImport.status === 'uploaded' ||
        props.currentImport.status === 'mapped' ||
        props.currentImport.status === 'failed'
    ) {
        return 2;
    }

    return 3;
});

const showMapping = computed(
    () =>
        props.currentImport != null &&
        ['uploaded', 'mapped', 'previewed', 'failed'].includes(
            props.currentImport.status,
        ),
);

const showExecute = computed(
    () =>
        props.currentImport != null &&
        ['previewed', 'failed', 'processing', 'completed'].includes(
            props.currentImport.status,
        ),
);

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    uploadForm.file = file;
}

function submitUpload(): void {
    uploadForm.post('/yoyu/money/imports', {
        forceFormData: true,
    });
}

function submitConfigure(): void {
    if (!props.currentImport) {
        return;
    }

    router.post(
        `/yoyu/money/imports/${props.currentImport.id}/configure`,
        {
            date_column: mapping.date_column,
            description_column: mapping.description_column || null,
            amount_column: mapping.amount_column || null,
            debit_column: mapping.debit_column || null,
            credit_column: mapping.credit_column || null,
            external_id_column: mapping.external_id_column || null,
            date_format: mapping.date_format || null,
            amount_sign: mapping.amount_sign || null,
            encoding: mapping.encoding || null,
            delimiter: mapping.delimiter || null,
            has_header: mapping.has_header,
        },
        { preserveScroll: true },
    );
}

function submitExecute(): void {
    if (!props.currentImport) {
        return;
    }

    if (
        !confirm(
            'プレビュー内容で取込を実行しますか？実データに反映されます。取消は一覧ページから可能です。',
        )
    ) {
        return;
    }

    router.post(
        `/yoyu/money/imports/${props.currentImport.id}/execute`,
        {},
        { preserveScroll: true },
    );
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
        title="CSV取込"
        :section-tabs="moneyLedgerTabs"
        section-active="imports-create"
        section-label="明細"
        primary-active="ledger"
    >
        <div class="flex items-center gap-2 text-[12px] text-os-sub">
            <Link href="/yoyu/money/imports" class="hover:text-os-ink">
                ← 取込一覧へ
            </Link>
        </div>

        <!-- Step indicator -->
        <ol class="flex flex-wrap gap-2 text-[12px] font-semibold">
            <li
                class="rounded-full px-3 py-1"
                :class="
                    step === 1
                        ? 'bg-os-yoyu-soft text-os-yoyu'
                        : 'border border-os-line bg-white text-os-sub'
                "
            >
                1. アップロード
            </li>
            <li
                class="rounded-full px-3 py-1"
                :class="
                    step === 2
                        ? 'bg-os-yoyu-soft text-os-yoyu'
                        : 'border border-os-line bg-white text-os-sub'
                "
            >
                2. マッピング
            </li>
            <li
                class="rounded-full px-3 py-1"
                :class="
                    step === 3
                        ? 'bg-os-yoyu-soft text-os-yoyu'
                        : 'border border-os-line bg-white text-os-sub'
                "
            >
                3. 実行
            </li>
        </ol>

        <!-- Step 1: Upload -->
        <section
            v-if="step === 1"
            class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-1 text-sm font-bold text-os-ink">
                CSVをアップロード
            </h2>
            <p class="mb-3 text-[12px] text-os-sub">
                銀行やカード会社からダウンロードしたCSVを選択してください。
            </p>
            <form class="space-y-3" @submit.prevent="submitUpload">
                <label class="block text-[12px] text-os-sub">
                    取込先口座
                    <select
                        v-model="uploadForm.account_id"
                        required
                        class="mt-1 block w-full rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    >
                        <option
                            v-for="account in accounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.name }}
                        </option>
                    </select>
                </label>
                <label class="block text-[12px] text-os-sub">
                    CSVファイル
                    <input
                        type="file"
                        accept=".csv,text/csv"
                        required
                        class="mt-1 block w-full text-[13px] text-os-ink"
                        @change="onFileChange"
                    />
                </label>
                <Button
                    type="submit"
                    size="sm"
                    class="rounded-full"
                    :disabled="uploadForm.processing || accounts.length === 0"
                >
                    アップロード
                </Button>
            </form>
            <p
                v-if="accounts.length === 0"
                class="mt-2 text-[12px] text-[#8A5A3B]"
            >
                先に口座を追加してください。
            </p>
        </section>

        <!-- Step 2: Mapping -->
        <section
            v-if="showMapping && currentImport"
            class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-1 text-sm font-bold text-os-ink">列マッピング</h2>
            <p class="mb-3 text-[12px] text-os-sub">
                {{ currentImport.source_filename }}（状態:
                {{ currentImport.status }}）
            </p>
            <form
                class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                @submit.prevent="submitConfigure"
            >
                <label class="text-[12px] text-os-sub">
                    日付列
                    <input
                        v-model="mapping.date_column"
                        type="text"
                        required
                        class="mt-1 block w-full rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    摘要列
                    <input
                        v-model="mapping.description_column"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    金額列
                    <input
                        v-model="mapping.amount_column"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    出金列
                    <input
                        v-model="mapping.debit_column"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    入金列
                    <input
                        v-model="mapping.credit_column"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    外部ID列
                    <input
                        v-model="mapping.external_id_column"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    日付形式
                    <input
                        v-model="mapping.date_format"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    区切り文字
                    <input
                        v-model="mapping.delimiter"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    文字コード
                    <input
                        v-model="mapping.encoding"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <label
                    class="col-span-2 flex items-center gap-2 text-[13px] text-os-ink sm:col-span-3"
                >
                    <input v-model="mapping.has_header" type="checkbox" />
                    1行目はヘッダー行
                </label>
                <div class="col-span-2 sm:col-span-3">
                    <Button type="submit" size="sm" class="rounded-full">
                        マッピングを保存してプレビュー
                    </Button>
                </div>
            </form>
        </section>

        <!-- Step 3: Execute -->
        <section
            v-if="showExecute && currentImport"
            class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-2 text-sm font-bold text-os-ink">取込実行</h2>
            <p class="text-[13px] text-os-sub">
                {{ currentImport.source_filename }}（{{
                    currentImport.row_count ?? 0
                }}
                行 / 状態: {{ currentImport.status }}）
            </p>
            <p
                class="mt-2 rounded-xl bg-os-yoyu-soft/60 px-3 py-2 text-[12px] text-os-ink"
            >
                実行すると取引が実データに登録されます。取消は取込一覧ページから可能です。
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <Button
                    v-if="
                        currentImport.status === 'previewed' ||
                        currentImport.status === 'failed'
                    "
                    type="button"
                    size="sm"
                    class="rounded-full"
                    @click="submitExecute"
                >
                    取込を実行する
                </Button>
            </div>
        </section>
    </MoneyPageShell>
</template>
