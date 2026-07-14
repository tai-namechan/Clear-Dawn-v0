export type KiokuLetterCharacterVariant = 'shiori' | 'nagi';

export type KiokuLetterVerdict = 'hit' | 'soft_hit' | 'miss' | 'sensitive_leak';

export type KiokuLetterItem = {
    id: string;
    position: number;
    memory_id: string;
    title: string;
    summary: string | null;
    headline: string;
    why_now: string;
    related: Array<{ id: string; title: string | null }>;
    verdict: KiokuLetterVerdict | null;
    verdict_note: string | null;
    verdict_at: string | null;
};

export type KiokuLetter = {
    id: string;
    week_start: string;
    week_end: string;
    status: string;
    character_variant: KiokuLetterCharacterVariant;
    intro: string | null;
    item_count: number;
    published_at: string | null;
    opened_at: string | null;
    completed_at: string | null;
    evaluation_memory_id: string | null;
    items: KiokuLetterItem[];
    verdict_counts: {
        judged: number;
        hit: number;
        soft_hit: number;
        miss: number;
        sensitive_leak: number;
    };
};

export type KiokuLetterSummary = {
    id: string;
    week_start: string;
    status: string;
    character_variant: KiokuLetterCharacterVariant;
    item_count: number;
    judged_count: number;
    hit_count: number;
    opened: boolean;
};
