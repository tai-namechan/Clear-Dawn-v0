/**
 * Clear Dawn のメインナビ定義。サイドバーとモバイル下部ナビで共有する。
 * 目標・動画はナビに置かず、ルーティン画面に入口を置く（表示中はルーティンを選択状態にする）。
 */

/** @typedef {'dashboard' | 'routines' | 'records' | 'meals' | 'settings'} ClearDawnNavKey */

/**
 * @typedef {object} ClearDawnNavItem
 * @property {ClearDawnNavKey} key
 * @property {string} label サイドバーの表示名
 * @property {string} shortLabel 下部ナビの表示名
 * @property {string} href
 * @property {string[]} paths 選択状態にするパスの前方一致
 */

/** @type {ClearDawnNavItem[]} */
export const clearDawnNavItems = [
    {
        key: 'dashboard',
        label: 'ダッシュボード',
        shortLabel: 'ホーム',
        href: '/dashboard',
        paths: ['/dashboard', '/life-areas'],
    },
    {
        key: 'routines',
        label: 'ルーティン',
        shortLabel: 'ルーティン',
        href: '/routines',
        paths: [
            '/routines',
            '/programs',
            '/plans',
            '/sessions',
            '/routine-items',
            '/goals',
            '/videos',
        ],
    },
    {
        key: 'records',
        label: 'パフォーマンス管理',
        shortLabel: '記録',
        href: '/records',
        paths: ['/records', '/history'],
    },
    {
        key: 'meals',
        label: '食事',
        shortLabel: '食事',
        href: '/meals',
        paths: ['/meals'],
    },
    {
        key: 'settings',
        label: '設定',
        shortLabel: '設定',
        href: '/settings/profile',
        paths: ['/settings'],
    },
];

/**
 * @param {string[]} paths
 * @param {string} currentPath クエリを含んでもよい
 * @returns {boolean}
 */
export function isNavPathActive(paths, currentPath) {
    const path = currentPath.split('?')[0] ?? '';

    return paths.some(
        (prefix) => path === prefix || path.startsWith(prefix + '/'),
    );
}
