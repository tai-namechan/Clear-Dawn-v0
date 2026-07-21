/**
 * Related-memory presentation helpers. Reasons stay explainable: shared tags
 * when visible, otherwise a generic non-AI claim.
 */

/**
 * @param {string[] | null | undefined} left
 * @param {string[] | null | undefined} right
 * @returns {string[]}
 */
export function sharedMemoryTags(left, right) {
    const rightSet = new Set(
        (right ?? []).filter((tag) => typeof tag === 'string' && tag !== ''),
    );

    /** @type {string[]} */
    const shared = [];

    for (const tag of left ?? []) {
        if (typeof tag !== 'string' || tag === '' || !rightSet.has(tag)) {
            continue;
        }

        if (!shared.includes(tag)) {
            shared.push(tag);
        }
    }

    return shared;
}

/**
 * @param {{ tags?: string[] | null }} memory
 * @param {{ tags?: string[] | null }} related
 * @returns {string}
 */
export function relatedMemoryReason(memory, related) {
    const shared = sharedMemoryTags(memory.tags, related.tags);

    if (shared.length > 0) {
        return `共通タグ: ${shared.map((tag) => `#${tag}`).join(' ')}`;
    }

    return '内容や関連付けから見つかりました';
}
