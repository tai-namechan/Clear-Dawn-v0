declare module '@/lib/kiokuLetter.mjs' {
    export type KiokuLetterCharacterMeta = {
        name: string;
        role: string;
        signature: string;
        theme: 'violet' | 'navy';
        width: number;
        height: number;
        colors: {
            accent: string;
            accentSoft: string;
            accentDeep: string;
            highlight: string;
        };
    };

    export const KIOKU_LETTER_CHARACTERS: Record<
        'shiori' | 'nagi',
        KiokuLetterCharacterMeta
    >;

    export function kiokuLetterCharacterMeta(
        variant: string | null | undefined,
    ): KiokuLetterCharacterMeta;

    export function kiokuLetterCharacterCssVars(
        variant: string | null | undefined,
    ): Record<string, string>;

    export type KiokuLetterVerdictOption = {
        value: string;
        label: string;
        description: string;
    };

    export const KIOKU_LETTER_VERDICTS: KiokuLetterVerdictOption[];

    export const KIOKU_LETTER_SENSITIVE_VERDICT: KiokuLetterVerdictOption;

    export const KIOKU_LETTER_EMPTY_MESSAGE: string;

    export function kiokuLetterPreviewMode(letter: {
        status: string;
        opened: boolean;
    }): 'empty' | 'unread' | 'in_progress' | 'done';

    export function kiokuLetterPreviewLabel(letter: {
        status: string;
        opened: boolean;
        item_count: number;
        judged_count: number;
        hit_count: number;
    }): string;

    export function kiokuLetterWeekLabel(weekStart: string): string;
}
