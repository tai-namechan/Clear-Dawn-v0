/** PFC 配色（ECharts は CSS 変数を解決できないため hex も併記） */
export const PFC_COLORS = {
    p: {
        css: 'var(--cd-pfc-p)',
        hex: '#3F9A70',
        className: 'text-cd-pfc-p',
        bgClassName: 'bg-cd-pfc-p',
    },
    f: {
        css: 'var(--cd-pfc-f)',
        hex: '#D58A38',
        className: 'text-cd-pfc-f',
        bgClassName: 'bg-cd-pfc-f',
    },
    c: {
        css: 'var(--cd-pfc-c)',
        hex: '#3B82C4',
        className: 'text-cd-pfc-c',
        bgClassName: 'bg-cd-pfc-c',
    },
} as const;
