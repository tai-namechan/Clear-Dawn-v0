import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    isMovVideoFile,
    resolveVideoMimeType,
} from '../../resources/js/lib/videoMimeType.mjs';

describe('resolveVideoMimeType', () => {
    it('keeps a concrete browser-provided type', () => {
        assert.equal(
            resolveVideoMimeType({ type: 'video/mp4', name: 'clip.mov' }),
            'video/mp4',
        );
    });

    it('maps .mov / empty type to video/quicktime', () => {
        assert.equal(
            resolveVideoMimeType({ type: '', name: 'IMG_8857.mov' }),
            'video/quicktime',
        );
        assert.equal(
            resolveVideoMimeType({
                type: 'application/octet-stream',
                name: 'clip.MOV',
            }),
            'video/quicktime',
        );
    });

    it('maps other known extensions', () => {
        assert.equal(
            resolveVideoMimeType({ type: '', name: 'a.webm' }),
            'video/webm',
        );
        assert.equal(
            resolveVideoMimeType({ type: '', name: 'a.m4v' }),
            'video/mp4',
        );
    });
});

describe('isMovVideoFile', () => {
    it('detects mov by type or extension', () => {
        assert.equal(
            isMovVideoFile({ type: 'video/quicktime', name: 'x.mp4' }),
            true,
        );
        assert.equal(isMovVideoFile({ type: '', name: 'x.mov' }), true);
        assert.equal(isMovVideoFile({ type: 'video/mp4', name: 'x.mp4' }), false);
        assert.equal(isMovVideoFile(null), false);
    });
});
