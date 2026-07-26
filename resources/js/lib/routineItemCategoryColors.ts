import type { RoutineItemCategory } from '@/types/routine';

export type RoutineItemCategoryColorClasses = {
    header: string;
    name: string;
};

/**
 * 実施項目カテゴリ → 見出し / 項目名の色。
 * 見出しは塗りつぶし白文字、項目名は別色のチップ（同系に寄せず派手め）。
 */
export const routineItemCategoryColorClasses: Record<
    RoutineItemCategory,
    RoutineItemCategoryColorClasses
> = {
    strength: {
        header: 'border-rose-600 bg-rose-600 text-white',
        name: 'bg-orange-400 text-orange-950',
    },
    baseball: {
        header: 'border-sky-600 bg-sky-600 text-white',
        name: 'bg-lime-300 text-lime-950',
    },
    mobility: {
        header: 'border-teal-600 bg-teal-600 text-white',
        name: 'bg-yellow-300 text-yellow-950',
    },
    care: {
        header: 'border-emerald-600 bg-emerald-600 text-white',
        name: 'bg-fuchsia-300 text-fuchsia-950',
    },
    music: {
        header: 'border-violet-600 bg-violet-600 text-white',
        name: 'bg-pink-400 text-pink-950',
    },
    study: {
        header: 'border-amber-500 bg-amber-500 text-amber-950',
        name: 'bg-sky-300 text-sky-950',
    },
    life: {
        header: 'border-indigo-600 bg-indigo-600 text-white',
        name: 'bg-rose-300 text-rose-950',
    },
    other: {
        header: 'border-slate-600 bg-slate-600 text-white',
        name: 'bg-amber-200 text-amber-950',
    },
};

export function categoryHeaderClasses(category: RoutineItemCategory): string {
    return (
        routineItemCategoryColorClasses[category]?.header ??
        routineItemCategoryColorClasses.other.header
    );
}

export function categoryNameClasses(category: RoutineItemCategory): string {
    return (
        routineItemCategoryColorClasses[category]?.name ??
        routineItemCategoryColorClasses.other.name
    );
}
