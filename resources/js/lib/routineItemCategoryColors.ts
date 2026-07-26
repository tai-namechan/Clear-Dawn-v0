import type { RoutineItemCategory } from '@/types/routine';

export type RoutineItemCategoryColorClasses = {
    header: string;
    name: string;
};

/**
 * 実施項目カテゴリ → 見出し / 項目名の色。
 * 見出しは淡い塗り、項目名は別色の薄いチップ（Clear Dawn の mealType トーンに合わせる）。
 */
export const routineItemCategoryColorClasses: Record<
    RoutineItemCategory,
    RoutineItemCategoryColorClasses
> = {
    strength: {
        header: 'border-rose-100 bg-rose-50 text-rose-800',
        name: 'bg-orange-50 text-orange-700',
    },
    baseball: {
        header: 'border-sky-100 bg-sky-50 text-sky-800',
        name: 'bg-teal-50 text-teal-700',
    },
    mobility: {
        header: 'border-teal-100 bg-teal-50 text-teal-800',
        name: 'bg-cyan-50 text-cyan-700',
    },
    care: {
        header: 'border-emerald-100 bg-emerald-50 text-emerald-800',
        name: 'bg-lime-50 text-lime-700',
    },
    music: {
        header: 'border-violet-100 bg-violet-50 text-violet-800',
        name: 'bg-fuchsia-50 text-fuchsia-700',
    },
    study: {
        header: 'border-amber-100 bg-amber-50 text-amber-800',
        name: 'bg-sky-50 text-sky-700',
    },
    life: {
        header: 'border-indigo-100 bg-indigo-50 text-indigo-800',
        name: 'bg-rose-50 text-rose-700',
    },
    other: {
        header: 'border-slate-200 bg-slate-50 text-slate-700',
        name: 'bg-stone-100 text-stone-700',
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
