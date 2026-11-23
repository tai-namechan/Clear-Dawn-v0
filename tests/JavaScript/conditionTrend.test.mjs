import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    trendAverage,
    trendAxisLabels,
} from '../../resources/js/lib/conditionTrend.mjs';

describe('trendAverage', () => {
    it('averages recorded values from the API strings', () => {
        assert.ok(Math.abs(trendAverage([{ value: '62.40' }, { value: '62.80' }]) - 62.6) < 1e-9);
        assert.equal(trendAverage([{ value: 3 }, { value: 2 }, { value: 4 }]), 3);
    });

    it('skips unusable values and returns null with no data', () => {
        assert.equal(trendAverage([{ value: 'x' }, { value: '4' }]), 4);
        assert.equal(trendAverage([]), null);
        assert.equal(trendAverage([{ value: '' }].filter((p) => p.value !== '')), null);
    });
});

describe('trendAxisLabels', () => {
    it('shows month only on the first day', () => {
        assert.deepEqual(
            trendAxisLabels(['2026-01-21', '2026-01-22', '2026-01-23']),
            ['1/21', '22', '23'],
        );
    });

    it('handles an empty range', () => {
        assert.deepEqual(trendAxisLabels([]), []);
    });
});
