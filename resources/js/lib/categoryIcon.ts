import {
    BookOpen,
    Dumbbell,
    Footprints,
    HeartPulse,
    Music,
    NotebookPen,
    Sparkles,
    Target,
} from '@lucide/vue';
import type { Component } from 'vue';
import type { RoutineItemCategory } from '@/types/routine';

/**
 * 実施項目カテゴリのアイコン。ルーティン一覧と「今日のセッション」で同じ対応表を使う。
 */
export function categoryIcon(
    category: RoutineItemCategory | null | undefined,
): Component {
    switch (category) {
        case 'strength':
            return Dumbbell;
        case 'baseball':
            return Target;
        case 'mobility':
            return Footprints;
        case 'care':
            return HeartPulse;
        case 'music':
            return Music;
        case 'study':
            return BookOpen;
        case 'life':
            return Sparkles;
        default:
            return NotebookPen;
    }
}
