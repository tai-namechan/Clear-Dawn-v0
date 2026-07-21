import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    buildKiokuHomeQuery,
    groupMemoriesByTag,
    KIOKU_UNTAGGED_LABEL,
    normalizeTagMode,
    toggleTagFilter,
    visibleTagCounts,
} from '../../resources/js/lib/kiokuTags.mjs';

describe('toggleTagFilter', () => {
    it('adds and removes while preserving original order', () => {
        assert.deepEqual(toggleTagFilter(['ヨガ'], '仕事'), ['ヨガ', '仕事']);
        assert.deepEqual(toggleTagFilter(['ヨガ', '仕事'], 'ヨガ'), ['仕事']);
    });

    it('lets a selected tag be cleared by clicking it again', () => {
        const selected = toggleTagFilter([], '自動化');
        assert.deepEqual(selected, ['自動化']);
        assert.deepEqual(toggleTagFilter(selected, '自動化'), []);
    });

    it('keeps candidate lists independent from selection state', () => {
        const candidates = [
            { tag: 'ヨガ', count: 3 },
            { tag: '仕事', count: 2 },
            { tag: '趣味', count: 1 },
        ];
        const selected = toggleTagFilter(['ヨガ'], '仕事');

        assert.deepEqual(
            candidates.map((item) => item.tag),
            ['ヨガ', '仕事', '趣味'],
        );
        assert.deepEqual(selected, ['ヨガ', '仕事']);
        assert.ok(candidates.every((item) => typeof item.count === 'number'));
    });
});

describe('buildKiokuHomeQuery', () => {
    it('keeps q/types and serializes tags', () => {
        assert.deepEqual(
            buildKiokuHomeQuery({
                q: 'Vite',
                types: ['error_log'],
                tags: ['仕事'],
                tagMode: 'and',
            }),
            {
                q: 'Vite',
                types: ['error_log'],
                tags: ['仕事'],
            },
        );
    });

    it('omits default AND and drops tag_mode when tags are empty', () => {
        assert.deepEqual(
            buildKiokuHomeQuery({
                q: null,
                types: [],
                tags: [],
                tagMode: 'or',
            }),
            {},
        );
        assert.deepEqual(
            buildKiokuHomeQuery({
                tags: ['ヨガ', '仕事'],
                tagMode: 'or',
            }),
            {
                tags: ['ヨガ', '仕事'],
                tag_mode: 'or',
            },
        );
    });
});

describe('normalizeTagMode', () => {
    it('maps invalid values to and', () => {
        assert.equal(normalizeTagMode('or'), 'or');
        assert.equal(normalizeTagMode('and'), 'and');
        assert.equal(normalizeTagMode('xor'), 'and');
        assert.equal(normalizeTagMode(null), 'and');
        assert.equal(normalizeTagMode(['or']), 'and');
    });
});

describe('groupMemoriesByTag', () => {
    it('places the same memory into multiple groups and keeps untagged last', () => {
        const a = { id: 'a', tags: ['ヨガ', '仕事'] };
        const b = { id: 'b', tags: ['ヨガ'] };
        const c = { id: 'c', tags: [] };
        const d = { id: 'd', tags: null };

        const groups = groupMemoriesByTag([a, b, c, d]);

        assert.equal(groups[0].tag, 'ヨガ');
        assert.deepEqual(
            groups[0].memories.map((memory) => memory.id),
            ['a', 'b'],
        );
        assert.equal(groups[1].tag, '仕事');
        assert.deepEqual(
            groups[1].memories.map((memory) => memory.id),
            ['a'],
        );

        const untagged = groups.at(-1);
        assert.equal(untagged.tag, KIOKU_UNTAGGED_LABEL);
        assert.equal(untagged.untagged, true);
        assert.deepEqual(
            untagged.memories.map((memory) => memory.id),
            ['c', 'd'],
        );
    });

    it('uses stable group ordering by size then label', () => {
        const groups = groupMemoriesByTag([
            { id: '1', tags: ['zebra'] },
            { id: '2', tags: ['apple'] },
            { id: '3', tags: ['apple'] },
        ]);

        assert.deepEqual(
            groups.map((group) => group.tag),
            ['apple', 'zebra'],
        );
    });
});

describe('visibleTagCounts', () => {
    it('counts unique tags per memory, orders by count, and limits', () => {
        const counts = visibleTagCounts(
            [
                { tags: ['ヨガ', 'ヨガ', '仕事'] },
                { tags: ['ヨガ'] },
                { tags: ['趣味'] },
                { tags: [null, '', 12] },
            ],
            2,
        );

        assert.deepEqual(counts, [
            { tag: 'ヨガ', count: 2 },
            { tag: '仕事', count: 1 },
        ]);
    });
});
