/**
 * Pure helpers for the Clear Dawn home (life matrix) screen.
 * Life areas carry no icon in the data, so the icon is inferred from the
 * area name; unknown names fall back to a neutral icon.
 */

/** @type {{ key: string, pattern: RegExp }[]} */
const AREA_ICON_RULES = [
    { key: 'ball', pattern: /野球|ベースボール|ソフトボール|球技|baseball/i },
    {
        key: 'music',
        pattern:
            /音楽|バイオリン|ヴァイオリン|ピアノ|ギター|楽器|music|violin/i,
    },
    { key: 'study', pattern: /学び|勉強|学習|受験|読書|資格|study/i },
    {
        key: 'health',
        pattern: /健康|体づくり|からだ|リカバリー|筋トレ|トレーニング|health/i,
    },
    { key: 'work', pattern: /仕事|ワーク|キャリア|業務|work/i },
    { key: 'private', pattern: /プライベート|生活|家族|趣味|private/i },
];

/**
 * @param {string} areaName
 * @returns {string} one of ball | music | study | health | work | private | default
 */
export function areaIconKey(areaName) {
    const name = String(areaName ?? '');

    return (
        AREA_ICON_RULES.find((rule) => rule.pattern.test(name))?.key ??
        'default'
    );
}

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

/**
 * @param {Date} date
 * @returns {string} e.g. "11月22日(日)"
 */
export function formatHomeDate(date) {
    return `${date.getMonth() + 1}月${date.getDate()}日(${WEEKDAYS[date.getDay()]})`;
}

/** @type {Record<string, string>} */
const FOCUS_TAB_LABELS = { monthly: '今月', current: '今', future: '将来' };

/**
 * @param {string} rowKey monthly | current | future
 * @param {string} fallback the row's own label
 * @returns {string}
 */
export function focusTabLabel(rowKey, fallback) {
    return FOCUS_TAB_LABELS[rowKey] ?? fallback;
}

/**
 * Number of items in one matrix cell (row × area).
 *
 * @param {{ key: string, cells: { items: unknown[] }[] }[]} rows
 * @param {string} rowKey
 * @param {number} areaIndex
 * @returns {number}
 */
export function cellItemCount(rows, rowKey, areaIndex) {
    return (
        rows.find((row) => row.key === rowKey)?.cells[areaIndex]?.items
            .length ?? 0
    );
}
