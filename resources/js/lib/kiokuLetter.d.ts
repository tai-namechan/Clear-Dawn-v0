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

    export const KIOKU_LETTER_EMPTY_MESSAGE_DAILY: string;

    export const KIOKU_LETTER_FAILED_MESSAGE: string;

    export const KIOKU_LETTER_HALTED_MESSAGE: string;

    export const KIOKU_LETTER_PAUSED_MESSAGE: string;

    export const KIOKU_LETTER_GROWING_MESSAGE: string;

    export function kiokuLetterPreviewMode(letter: {
        status: string;
        opened: boolean;
    }): 'empty' | 'unread' | 'in_progress' | 'done' | 'failed' | 'halted';

    export function kiokuLetterHomeMode(
        letter: { status: string; opened: boolean } | null | undefined,
        schedule?: { state?: string } | null,
    ):
        | 'none'
        | 'growing'
        | 'schedule_halted'
        | 'schedule_paused'
        | 'failed'
        | 'halted'
        | 'empty'
        | 'unread'
        | 'in_progress'
        | 'done';

    export function groupKiokuLetterHistory<T extends { id: string; status: string }>(
        letters: T[],
    ): Array<
        | { type: 'letter'; letter: T }
        | { type: 'empty_run'; count: number; letters: T[] }
    >;

    export function kiokuLetterPreviewLabel(letter: {
        status: string;
        opened: boolean;
        item_count: number;
        judged_count: number;
        hit_count: number;
        cadence?: string;
    }): string;

    export function kiokuLetterWeekLabel(weekStart: string): string;

    export function kiokuLetterDailyLabel(deliveryDate: string): string;

    export function kiokuLetterTitleLabel(letter: {
        cadence?: string;
        delivery_date?: string;
        week_start: string;
    }): string;
}
