export type MoneyAmountDto = {
    amountMinor: string;
    currency: string;
};

export type MoneyAccountRow = {
    id: string;
    name: string;
    type: string;
    currency_code: string;
    current_balance: MoneyAmountDto;
    available_balance: MoneyAmountDto | null;
    balance_as_of: string | null;
    is_active: boolean;
    lock_version: number;
};

export type MoneyCashflowRow = {
    id: string;
    name: string;
    direction: string;
    kind: string;
    status: string;
    certainty: string;
    due_on: string;
    amount: MoneyAmountDto;
    lock_version: number;
};

export type MoneyCardRow = {
    id: string;
    name: string;
    issuer_name: string | null;
    currency_code: string;
    closing_day: string;
    payment_day: string;
    available: MoneyAmountDto | null;
    current_statement: MoneyAmountDto | null;
    unconfirmed: MoneyAmountDto | null;
    is_active: boolean;
    lock_version: number;
};

export type MoneyLoanRow = {
    id: string;
    name: string;
    type: string;
    status: string;
    currency_code: string;
    outstanding_principal: MoneyAmountDto;
    monthly_payment: MoneyAmountDto;
    next_payment_on: string;
    lock_version: number;
};

export type MoneyTransactionRow = {
    id: string;
    account_id: string;
    direction: string;
    kind: string;
    status: string;
    occurred_on: string;
    description: string | null;
    amount: MoneyAmountDto;
    voided_at: string | null;
};

export type MoneyImportRow = {
    id: string;
    account_id: string;
    status: string;
    source_filename: string | null;
    row_count: number | null;
    created_at: string | null;
};

export type MoneySimulationRow = {
    id: string;
    name: string | null;
    status: string;
    base_date: string;
    horizon_months: number;
    created_at: string | null;
    result_payload?: Record<string, unknown> | null;
};

export type MoneyDecisionRow = {
    id: string;
    title: string;
    status: string;
    decided_on: string;
    reviewed_at: string | null;
    memo: string | null;
    before_payload?: Record<string, unknown> | null;
    expected_effect_payload?: Record<string, unknown> | null;
    actual_effect_payload?: Record<string, unknown> | null;
};

export type MoneyCategoryRow = {
    id: string;
    name: string;
    direction_scope: string;
    flexibility_default: string;
    cost_behavior_default: string | null;
    is_essential: boolean;
    is_active: boolean;
    sort_order: number;
};
