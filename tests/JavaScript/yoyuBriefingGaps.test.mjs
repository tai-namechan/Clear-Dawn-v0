import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { joinGapsWithSuggestions } from '../../resources/js/lib/yoyuBriefingGaps.mjs';

const analysis3 = [
    { key: 'gap_1', start: '09:00', end: '10:00', minutes: 60 },
    { key: 'gap_2', start: '11:00', end: '12:00', minutes: 60 },
    { key: 'gap_3', start: '14:00', end: '16:00', minutes: 120 },
];

describe('joinGapsWithSuggestions', () => {
    it('shows all analysis gaps when only one suggestion exists', () => {
        const joined = joinGapsWithSuggestions(analysis3, [
            { gap_key: 'gap_2', suggestion: '休憩' },
        ]);

        assert.equal(joined.length, 3);
        assert.equal(joined[0].suggestion, '');
        assert.equal(joined[1].suggestion, '休憩');
        assert.equal(joined[2].suggestion, '');
        assert.deepEqual(
            joined.map((g) => g.gap_key),
            ['gap_1', 'gap_2', 'gap_3'],
        );
    });

    it('keeps all gaps when suggestions are empty', () => {
        const joined = joinGapsWithSuggestions(analysis3, []);
        assert.equal(joined.length, 3);
        assert.ok(joined.every((g) => g.suggestion === ''));
    });

    it('attaches suggestions for all matching gaps', () => {
        const joined = joinGapsWithSuggestions(analysis3, [
            { gap_key: 'gap_1', suggestion: 'a' },
            { gap_key: 'gap_2', suggestion: 'b' },
            { gap_key: 'gap_3', suggestion: 'c' },
        ]);
        assert.deepEqual(
            joined.map((g) => g.suggestion),
            ['a', 'b', 'c'],
        );
    });

    it('drops foreign suggestion keys', () => {
        const joined = joinGapsWithSuggestions(analysis3, [
            { gap_key: 'gap_99', suggestion: 'ghost' },
            { gap_key: 'gap_1', suggestion: 'keep' },
        ]);
        assert.equal(joined.length, 3);
        assert.equal(joined[0].suggestion, 'keep');
        assert.ok(!joined.some((g) => g.suggestion === 'ghost'));
    });

    it('preserves analysis order', () => {
        const joined = joinGapsWithSuggestions(analysis3, [
            { gap_key: 'gap_3', suggestion: 'last-first-in-ai' },
            { gap_key: 'gap_1', suggestion: 'first' },
        ]);
        assert.deepEqual(
            joined.map((g) => g.gap_key),
            ['gap_1', 'gap_2', 'gap_3'],
        );
        assert.equal(joined[0].start, '09:00');
        assert.equal(joined[2].minutes, 120);
    });
});
