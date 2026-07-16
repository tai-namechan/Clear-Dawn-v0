/**
 * Tag filter + tag view logic for Kioku Home
 * (docs/architecture/kioku-knowledge-retrieval.md §3).
 *
 * Pure data helpers so node --test can verify AND/OR state, URL query
 * serialization and grouping without Vue. Tag views are built from the
 * memories already in the page response — one memory may appear in every
 * group of its tags (views never duplicate the stored Memory).
 */

export const KIOKU_UNTAGGED_LABEL = '未分類';

/**
 * @param {string[]} tags
 * @param {string} tag
 * @returns {string[]} new array with tag toggled, original order kept
 */
export function toggleTagFilter(tags, tag) {
    return tags.includes(tag)
        ? tags.filter((current) => current !== tag)
        : [...tags, tag];
}

/**
 * Query object for the Home URL. Defaults are omitted so plain URLs stay
 * backward compatible: no tags → no tags/tag_mode keys, and tag_mode is
 * only serialized when it differs from the default 'and'.
 *
 * @param {{ q?: string | null, types?: string[], tags?: string[], tagMode?: 'and' | 'or' }} state
 * @returns {Record<string, unknown>}
 */
export function buildKiokuHomeQuery(state) {
    const query = {};

    if (state.q) {
        query.q = state.q;
    }

    if (state.types && state.types.length > 0) {
        query.types = state.types;
    }

    const tags = state.tags ?? [];
    if (tags.length > 0) {
        query.tags = tags;

        if ((state.tagMode ?? 'and') === 'or') {
            query.tag_mode = 'or';
        }
    }

    return query;
}

/**
 * @param {unknown} value
 * @returns {'and' | 'or'}
 */
export function normalizeTagMode(value) {
    return value === 'or' ? 'or' : 'and';
}

/**
 * Group the visible memories by tag for the tag view. The same memory
 * appears in each of its tag groups; memories without tags fall into the
 * trailing untagged group. Groups are ordered by size (desc), then tag
 * label (asc) for a stable layout.
 *
 * @param {Array<{ id: string, tags?: string[] | null }>} memories
 * @returns {Array<{ tag: string, untagged: boolean, memories: Array<{ id: string }> }>}
 */
export function groupMemoriesByTag(memories) {
    const groups = new Map();
    const untagged = [];

    for (const memory of memories) {
        const tags = (memory.tags ?? []).filter(
            (tag) => typeof tag === 'string' && tag !== '',
        );

        if (tags.length === 0) {
            untagged.push(memory);
            continue;
        }

        for (const tag of new Set(tags)) {
            if (!groups.has(tag)) {
                groups.set(tag, []);
            }

            groups.get(tag).push(memory);
        }
    }

    const result = [...groups.entries()]
        .map(([tag, grouped]) => ({ tag, untagged: false, memories: grouped }))
        .sort(
            (a, b) =>
                b.memories.length - a.memories.length ||
                (a.tag < b.tag ? -1 : a.tag > b.tag ? 1 : 0),
        );

    if (untagged.length > 0) {
        result.push({
            tag: KIOKU_UNTAGGED_LABEL,
            untagged: true,
            memories: untagged,
        });
    }

    return result;
}

/**
 * Tag counts across the currently visible memories only (never a second
 * fetch), ordered by count desc then label asc.
 *
 * @param {Array<{ tags?: string[] | null }>} memories
 * @param {number} limit
 * @returns {Array<{ tag: string, count: number }>}
 */
export function visibleTagCounts(memories, limit = 15) {
    const counts = new Map();

    for (const memory of memories) {
        for (const tag of new Set(memory.tags ?? [])) {
            if (typeof tag !== 'string' || tag === '') {
                continue;
            }

            counts.set(tag, (counts.get(tag) ?? 0) + 1);
        }
    }

    return [...counts.entries()]
        .map(([tag, count]) => ({ tag, count }))
        .sort(
            (a, b) =>
                b.count - a.count ||
                (a.tag < b.tag ? -1 : a.tag > b.tag ? 1 : 0),
        )
        .slice(0, limit);
}
