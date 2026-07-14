import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    formatKiokuAudioClock,
    kiokuAudioDurationSeconds,
} from '../../resources/js/lib/kiokuAudioDuration.mjs';

describe('kiokuAudioDurationSeconds', () => {
    it('converts positive ms to seconds', () => {
        assert.equal(kiokuAudioDurationSeconds(12000), 12);
        assert.equal(kiokuAudioDurationSeconds(1500), 1.5);
    });

    it('returns null for missing or non-positive values', () => {
        assert.equal(kiokuAudioDurationSeconds(null), null);
        assert.equal(kiokuAudioDurationSeconds(undefined), null);
        assert.equal(kiokuAudioDurationSeconds(0), null);
        assert.equal(kiokuAudioDurationSeconds(-1), null);
    });
});

describe('formatKiokuAudioClock', () => {
    it('formats m:ss from seconds', () => {
        assert.equal(formatKiokuAudioClock(0), '0:00');
        assert.equal(formatKiokuAudioClock(12), '0:12');
        assert.equal(formatKiokuAudioClock(65), '1:05');
    });

    it('floors fractional seconds and guards nullish', () => {
        assert.equal(formatKiokuAudioClock(12.9), '0:12');
        assert.equal(formatKiokuAudioClock(null), '0:00');
        assert.equal(formatKiokuAudioClock(Number.NaN), '0:00');
    });
});
