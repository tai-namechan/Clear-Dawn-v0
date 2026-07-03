import type { LifeAreaColor } from '@/types/matrix';

/** パレットキー → 表示スウォッチ用クラス（トークンは app.css で定義） */
export const lifeAreaColorClasses: Record<LifeAreaColor, string> = {
    dawn: 'bg-cd-dawn-soft',
    sunrise: 'bg-cd-sunrise',
    gilt: 'bg-cd-gilt',
    moss: 'bg-cd-moss',
    mist: 'bg-cd-mist',
    lavender: 'bg-cd-lavender-mist',
};

export const lifeAreaColorLabels: Record<LifeAreaColor, string> = {
    dawn: '夜明け',
    sunrise: '朝焼け',
    gilt: '金色',
    moss: '苔',
    mist: '霧',
    lavender: 'ラベンダー',
};

export const lifeAreaColorOptions = (
    Object.keys(lifeAreaColorLabels) as LifeAreaColor[]
).map((value) => ({
    value,
    label: lifeAreaColorLabels[value],
}));
