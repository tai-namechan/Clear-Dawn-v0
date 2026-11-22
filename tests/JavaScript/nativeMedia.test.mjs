import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    isUserCancellation,
    nativePhotoFileName,
    normalizeScannedCode,
} from '../../resources/js/lib/nativeMedia.mjs';

describe('nativePhotoFileName', () => {
    const takenAt = new Date(2026, 10, 23, 8, 5, 9);

    it('stamps the local time and picks the extension from the MIME type', () => {
        assert.equal(
            nativePhotoFileName('image/jpeg', takenAt, 'meal'),
            'meal-20261123-080509.jpg',
        );
        assert.equal(
            nativePhotoFileName('image/PNG', takenAt),
            'photo-20261123-080509.png',
        );
        assert.equal(
            nativePhotoFileName('image/heic', takenAt, 'label'),
            'label-20261123-080509.heic',
        );
    });

    it('falls back to jpg for unknown types', () => {
        assert.equal(
            nativePhotoFileName('application/octet-stream', takenAt),
            'photo-20261123-080509.jpg',
        );
    });
});

describe('normalizeScannedCode', () => {
    it('trims what the scanner returned', () => {
        assert.equal(normalizeScannedCode(' 4901234567890\n'), '4901234567890');
    });

    it('returns null when nothing was read', () => {
        assert.equal(normalizeScannedCode(''), null);
        assert.equal(normalizeScannedCode('   '), null);
        assert.equal(normalizeScannedCode(undefined), null);
    });
});

describe('isUserCancellation', () => {
    it('recognises cancel messages from the camera and scanner', () => {
        assert.equal(
            isUserCancellation(new Error('User cancelled photos app')),
            true,
        );
        assert.equal(isUserCancellation({ message: 'Scan canceled' }), true);
        assert.equal(isUserCancellation('No image picked'), true);
    });

    it('treats other errors as real failures', () => {
        assert.equal(
            isUserCancellation(new Error('Camera permission denied by system')),
            false,
        );
        assert.equal(isUserCancellation(null), false);
    });
});
