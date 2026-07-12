import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    fileExtensionForAudioMime,
    formatRecordingElapsed,
    KIOKU_MAX_RECORDING_MS,
    pickSupportedAudioMimeType,
    shouldAutoStopRecording,
} from '../../resources/js/lib/kiokuAudioRecorder.mjs';

describe('pickSupportedAudioMimeType', () => {
    it('prefers mp4 when supported (Safari)', () => {
        const picked = pickSupportedAudioMimeType(
            (type) => type === 'audio/mp4',
        );

        assert.equal(picked, 'audio/mp4');
    });

    it('falls back to webm/opus (Chrome)', () => {
        const picked = pickSupportedAudioMimeType((type) =>
            type.startsWith('audio/webm'),
        );

        assert.equal(picked, 'audio/webm;codecs=opus');
    });

    it('returns null when nothing is supported', () => {
        assert.equal(
            pickSupportedAudioMimeType(() => false),
            null,
        );
    });

    it('survives engines that throw on unknown types', () => {
        const picked = pickSupportedAudioMimeType((type) => {
            if (type !== 'audio/ogg') {
                throw new Error('unknown type');
            }

            return true;
        });

        assert.equal(picked, 'audio/ogg');
    });
});

describe('recording limits', () => {
    it('auto-stops exactly at the 3-minute cap', () => {
        assert.equal(
            shouldAutoStopRecording(KIOKU_MAX_RECORDING_MS - 1),
            false,
        );
        assert.equal(shouldAutoStopRecording(KIOKU_MAX_RECORDING_MS), true);
    });

    it('respects a custom cap', () => {
        assert.equal(shouldAutoStopRecording(4_999, 5_000), false);
        assert.equal(shouldAutoStopRecording(5_000, 5_000), true);
    });
});

describe('formatRecordingElapsed', () => {
    it('formats as M:SS', () => {
        assert.equal(formatRecordingElapsed(0), '0:00');
        assert.equal(formatRecordingElapsed(61_000), '1:01');
        assert.equal(formatRecordingElapsed(180_000), '3:00');
        assert.equal(formatRecordingElapsed(-5), '0:00');
    });
});

describe('fileExtensionForAudioMime', () => {
    it('maps recorder MIME types to upload extensions', () => {
        assert.equal(
            fileExtensionForAudioMime('audio/webm;codecs=opus'),
            'webm',
        );
        assert.equal(fileExtensionForAudioMime('audio/mp4'), 'm4a');
        assert.equal(fileExtensionForAudioMime('audio/ogg'), 'ogg');
        assert.equal(fileExtensionForAudioMime('audio/wav'), 'wav');
        assert.equal(fileExtensionForAudioMime(null), 'bin');
        assert.equal(fileExtensionForAudioMime('text/plain'), 'bin');
    });
});
