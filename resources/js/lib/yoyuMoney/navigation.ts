export type MoneyPrimaryNavKey =
    'home' | 'month' | 'assets' | 'ledger' | 'plan' | 'settings';

export type MoneySectionTab = {
    key: string;
    label: string;
    href: string;
};

export type MoneyPrimaryNavItem = {
    key: MoneyPrimaryNavKey;
    label: string;
    href: string;
    matchPrefixes: string[];
};

export const moneyPrimaryNav: MoneyPrimaryNavItem[] = [
    {
        key: 'home',
        label: 'ホーム',
        href: '/yoyu/money',
        matchPrefixes: ['/yoyu/money'],
    },
    {
        key: 'month',
        label: '今月',
        href: '/yoyu/money/cashflows',
        matchPrefixes: ['/yoyu/money/cashflows'],
    },
    {
        key: 'assets',
        label: '資産・返済',
        href: '/yoyu/money/accounts',
        matchPrefixes: [
            '/yoyu/money/accounts',
            '/yoyu/money/cards',
            '/yoyu/money/loans',
        ],
    },
    {
        key: 'ledger',
        label: '明細',
        href: '/yoyu/money/transactions',
        matchPrefixes: ['/yoyu/money/transactions', '/yoyu/money/imports'],
    },
    {
        key: 'plan',
        label: '計画',
        href: '/yoyu/money/analysis',
        matchPrefixes: [
            '/yoyu/money/analysis',
            '/yoyu/money/simulations',
            '/yoyu/money/decisions',
        ],
    },
];

export const moneyAssetsTabs: MoneySectionTab[] = [
    { key: 'accounts', label: '口座', href: '/yoyu/money/accounts' },
    { key: 'cards', label: 'カード', href: '/yoyu/money/cards' },
    { key: 'loans', label: 'ローン', href: '/yoyu/money/loans' },
];

export const moneyLedgerTabs: MoneySectionTab[] = [
    {
        key: 'transactions',
        label: '取引明細',
        href: '/yoyu/money/transactions',
    },
    {
        key: 'imports-create',
        label: 'CSV取込',
        href: '/yoyu/money/imports/create',
    },
    { key: 'imports', label: '取込履歴', href: '/yoyu/money/imports' },
];

export const moneyPlanTabs: MoneySectionTab[] = [
    { key: 'analysis', label: '分析', href: '/yoyu/money/analysis' },
    {
        key: 'simulations',
        label: 'シミュレーター',
        href: '/yoyu/money/simulations',
    },
    { key: 'decisions', label: '見直したこと', href: '/yoyu/money/decisions' },
];

export const moneyRecordMenu: MoneySectionTab[] = [
    {
        key: 'income',
        label: '収入予定',
        href: '/yoyu/money/cashflows?compose=income',
    },
    {
        key: 'expense',
        label: '支払予定',
        href: '/yoyu/money/cashflows?compose=expense',
    },
    {
        key: 'transaction',
        label: '取引明細',
        href: '/yoyu/money/transactions?compose=1',
    },
    {
        key: 'account',
        label: '口座残高',
        href: '/yoyu/money/accounts?compose=1',
    },
    { key: 'card', label: 'カード', href: '/yoyu/money/cards?compose=1' },
    { key: 'loan', label: 'ローン', href: '/yoyu/money/loans?compose=1' },
];

export function resolveMoneyPrimaryNav(url: string): MoneyPrimaryNavKey {
    const path = url.split('?')[0] ?? url;

    if (path === '/yoyu/money' || path === '/yoyu/money/') {
        return 'home';
    }

    if (path.startsWith('/yoyu/money/settings')) {
        return 'settings';
    }

    if (path.startsWith('/yoyu/money/cashflows')) {
        return 'month';
    }

    if (
        path.startsWith('/yoyu/money/accounts') ||
        path.startsWith('/yoyu/money/cards') ||
        path.startsWith('/yoyu/money/loans')
    ) {
        return 'assets';
    }

    if (
        path.startsWith('/yoyu/money/transactions') ||
        path.startsWith('/yoyu/money/imports')
    ) {
        return 'ledger';
    }

    if (
        path.startsWith('/yoyu/money/analysis') ||
        path.startsWith('/yoyu/money/simulations') ||
        path.startsWith('/yoyu/money/decisions')
    ) {
        return 'plan';
    }

    return 'home';
}
