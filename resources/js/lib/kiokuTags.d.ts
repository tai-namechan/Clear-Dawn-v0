declare module '@/lib/kiokuTags.mjs' {
    export const KIOKU_UNTAGGED_LABEL: string;
    export const KIOKU_MAX_TAGS: number;
    export const KIOKU_MAX_TAG_CHARS: number;

    export function toggleTagFilter(tags: string[], tag: string): string[];

    export function buildKiokuHomeQuery(state: {
        q?: string | null;
        types?: string[];
        tags?: string[];
        tagMode?: 'and' | 'or';
    }): {
        q?: string;
        types?: string[];
        tags?: string[];
        tag_mode?: 'or';
    };

    export function normalizeTagMode(value: unknown): 'and' | 'or';

    export function groupMemoriesByTag<
        T extends { id: string; tags?: string[] | null },
    >(memories: T[]): Array<{ tag: string; untagged: boolean; memories: T[] }>;

    export function visibleTagCounts(
        memories: Array<{ tags?: string[] | null }>,
        limit?: number,
    ): Array<{ tag: string; count: number }>;
}
