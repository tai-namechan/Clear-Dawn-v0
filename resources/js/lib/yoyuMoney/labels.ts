const DIRECTION_LABELS: Record<string, string> = {
    inflow: '入金',
    outflow: '支払い',
};

const CERTAINTY_LABELS: Record<string, string> = {
    confirmed: '確定',
    expected: '見込み',
};

const FLEXIBILITY_LABELS: Record<string, string> = {
    required: '必須',
    adjustable: '調整可能',
    stoppable: '停止可能',
};

const CASHFLOW_STATUS_LABELS: Record<string, string> = {
    planned: '未処理',
    partial: '一部処理',
    settled: '処理済み',
    deferred: '延期',
    canceled: '取消',
};

const TRANSACTION_KIND_LABELS: Record<string, string> = {
    purchase: '利用',
    income: '収入',
    fee: '手数料',
    interest: '利息',
    refund: '返金',
    card_payment: 'カード請求支払い',
    loan_payment: 'ローン返済',
    transfer: '振替',
    adjustment: '調整',
};

const TRANSACTION_SPEND_HINTS: Record<string, string> = {
    purchase: '支出計算済み',
    fee: '支出計算済み',
    interest: '支出計算済み',
    refund: '支出から減算',
    card_payment: 'カード請求に含まれるため参考表示',
    loan_payment: '返済として記録',
    transfer: '振替のため支出集計外',
    adjustment: '調整のため参考表示',
    income: '収入',
};

const ACCOUNT_TYPE_LABELS: Record<string, string> = {
    bank: '銀行',
    cash: '現金',
    electronic_money: '電子マネー',
    other: 'その他',
};

const LOAN_STATUS_LABELS: Record<string, string> = {
    active: '返済中',
    paused: '一時停止',
    paid_off: '完済',
    written_off: '償却',
};

const SIMULATION_STATUS_LABELS: Record<string, string> = {
    draft: '下書き',
    calculated: '計算済み',
    applied: '反映済み',
    discarded: '破棄',
    stale: 'データ更新あり',
};

const DECISION_STATUS_LABELS: Record<string, string> = {
    recorded: '記録済み',
    reviewed: '振り返り済み',
    archived: 'アーカイブ',
};

const MISSING_SETTING_LABELS: Record<string, string> = {
    minimum_living_budget_minor: '最低生活費',
    safety_buffer_minor: '安全資金',
};

function labelFrom(
    map: Record<string, string>,
    value: string | null | undefined,
    fallback = value ?? '—',
): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return map[value] ?? fallback;
}

export function directionLabel(value: string): string {
    return labelFrom(DIRECTION_LABELS, value);
}

export function certaintyLabel(value: string): string {
    return labelFrom(CERTAINTY_LABELS, value);
}

export function flexibilityLabel(value: string): string {
    return labelFrom(FLEXIBILITY_LABELS, value);
}

export function cashflowStatusLabel(value: string): string {
    return labelFrom(CASHFLOW_STATUS_LABELS, value);
}

export function transactionKindLabel(value: string): string {
    return labelFrom(TRANSACTION_KIND_LABELS, value);
}

export function transactionSpendHint(value: string): string {
    return labelFrom(TRANSACTION_SPEND_HINTS, value, '状態を確認');
}

export function accountTypeLabel(value: string): string {
    return labelFrom(ACCOUNT_TYPE_LABELS, value);
}

export function loanStatusLabel(value: string): string {
    return labelFrom(LOAN_STATUS_LABELS, value);
}

export function simulationStatusLabel(value: string): string {
    return labelFrom(SIMULATION_STATUS_LABELS, value);
}

export function decisionStatusLabel(value: string): string {
    return labelFrom(DECISION_STATUS_LABELS, value);
}

export function missingSettingLabel(value: string): string {
    return labelFrom(MISSING_SETTING_LABELS, value);
}

export function formatMonthLabel(month: string): string {
    const match = /^(\d{4})-(\d{2})$/.exec(month);

    if (!match) {
        return month;
    }

    return `${match[1]}年${Number(match[2])}月`;
}

export function shiftMonth(month: string, delta: number): string {
    const match = /^(\d{4})-(\d{2})$/.exec(month);

    if (!match) {
        return month;
    }

    const year = Number(match[1]);
    const monthIndex = Number(match[2]) - 1 + delta;
    const date = new Date(Date.UTC(year, monthIndex, 1));
    const y = date.getUTCFullYear();
    const m = String(date.getUTCMonth() + 1).padStart(2, '0');

    return `${y}-${m}`;
}
