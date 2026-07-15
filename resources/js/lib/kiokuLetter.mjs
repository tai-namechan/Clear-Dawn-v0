/**
 * Concierge letter presentation logic
 * (docs/product/kioku-final-remaining-implementation.md §8, §15 +
 * docs/product/kioku-concierge-daily-pilot.md).
 *
 * Characters share candidates, AI body, item order and verdict UI; the only
 * differences are the image, the CSS theme colors, and the fixed signature
 * defined here. Keeping this data in a plain module lets node --test verify
 * both variants are complete without loading Vue or Vite assets.
 */

export const KIOKU_LETTER_CHARACTERS = {
    shiori: {
        name: 'シオリ',
        role: '記憶の案内役',
        signature: '記憶の案内役 シオリ',
        theme: 'violet',
        width: 639,
        height: 960,
        colors: {
            /* 紫（本文アクセント）と琥珀（強調） */
            accent: '#6D5FA8',
            accentSoft: '#EFEBF6',
            accentDeep: '#57497E',
            highlight: '#B8862B',
        },
    },
    nagi: {
        name: 'ナギ',
        role: '記憶の配達役',
        signature: '記憶の配達役 ナギ',
        theme: 'navy',
        width: 640,
        height: 960,
        colors: {
            /* 紺（本文アクセント）とセージ（強調） */
            accent: '#3E5688',
            accentSoft: '#E9EDF5',
            accentDeep: '#324670',
            highlight: '#5D8A5F',
        },
    },
};

/**
 * @param {string | null | undefined} variant
 */
export function kiokuLetterCharacterMeta(variant) {
    if (variant && variant in KIOKU_LETTER_CHARACTERS) {
        return KIOKU_LETTER_CHARACTERS[variant];
    }

    return KIOKU_LETTER_CHARACTERS.shiori;
}

/**
 * CSS custom properties for the letter page. Only these variables (plus the
 * image and signature) may differ between characters — the DOM stays
 * identical.
 *
 * @param {string | null | undefined} variant
 * @returns {Record<string, string>}
 */
export function kiokuLetterCharacterCssVars(variant) {
    const meta = kiokuLetterCharacterMeta(variant);

    return {
        '--letter-accent': meta.colors.accent,
        '--letter-accent-soft': meta.colors.accentSoft,
        '--letter-accent-deep': meta.colors.accentDeep,
        '--letter-highlight': meta.colors.highlight,
    };
}

/** Order is fixed; sensitive_leak is intentionally NOT in this list — it is
 * rendered apart from the three normal verdicts and needs confirmation. */
export const KIOKU_LETTER_VERDICTS = [
    { value: 'hit', label: 'HIT', description: '忘れていた・今必要だった' },
    {
        value: 'soft_hit',
        label: 'SOFT HIT',
        description: '覚えていたが再提示に意味があった',
    },
    { value: 'miss', label: 'MISS', description: '今回は違った' },
];

export const KIOKU_LETTER_SENSITIVE_VERDICT = {
    value: 'sensitive_leak',
    label: '表示すべきでない記憶',
    description: '出したくない記憶が表示された（手紙の生成を停止します）',
};

export const KIOKU_LETTER_EMPTY_MESSAGE =
    '今週は、無理に届ける記憶はありませんでした。';

export const KIOKU_LETTER_EMPTY_MESSAGE_DAILY =
    '今日は、無理に届ける記憶はありませんでした。';

/**
 * Home preview mode for one letter summary.
 *
 * @param {{ status: string, opened: boolean }} letter
 * @returns {'empty' | 'unread' | 'in_progress' | 'done'}
 */
export function kiokuLetterPreviewMode(letter) {
    if (letter.status === 'empty') {
        return 'empty';
    }

    if (letter.status === 'published' && !letter.opened) {
        return 'unread';
    }

    if (letter.status === 'evaluated') {
        return 'done';
    }

    return 'in_progress';
}

/**
 * @param {{ status: string, opened: boolean, item_count: number, judged_count: number, hit_count: number, cadence?: string }} letter
 */
export function kiokuLetterPreviewLabel(letter) {
    switch (kiokuLetterPreviewMode(letter)) {
        case 'empty':
            return letter.cadence === 'daily'
                ? KIOKU_LETTER_EMPTY_MESSAGE_DAILY
                : KIOKU_LETTER_EMPTY_MESSAGE;
        case 'unread':
            return '未読の便りが届いています';
        case 'done':
            return `HIT ${letter.hit_count} / ${letter.item_count}件`;
        default:
            return `${letter.item_count}件中${letter.judged_count}件を判定済み`;
    }
}

/**
 * @param {string} weekStart ISO date (YYYY-MM-DD)
 */
export function kiokuLetterWeekLabel(weekStart) {
    const [, month, day] = weekStart.split('-').map(Number);

    if (!month || !day) {
        return weekStart;
    }

    return `${month}/${day}の週`;
}

/**
 * @param {string} deliveryDate ISO date (YYYY-MM-DD)
 */
export function kiokuLetterDailyLabel(deliveryDate) {
    const [year, month, day] = deliveryDate.split('-').map(Number);

    if (!year || !month || !day) {
        return deliveryDate;
    }

    return `${year}/${month}/${day}のキオク便り`;
}

/**
 * @param {{ cadence?: string, delivery_date?: string, week_start: string }} letter
 */
export function kiokuLetterTitleLabel(letter) {
    if (letter.cadence === 'daily' && letter.delivery_date) {
        return kiokuLetterDailyLabel(letter.delivery_date);
    }

    return `${kiokuLetterWeekLabel(letter.week_start)}のキオク便り`;
}
