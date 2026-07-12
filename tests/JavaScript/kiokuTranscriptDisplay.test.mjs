import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { kiokuTranscriptDisplayMode } from '../../resources/js/lib/kiokuTranscriptDisplay.mjs';

describe('kiokuTranscriptDisplayMode', () => {
    it('shows text when transcript is non-empty', () => {
        assert.equal(
            kiokuTranscriptDisplayMode({
                transcriptionEnabled: true,
                transcriptionStatus: 'ready',
                transcriptText: '残した一文',
            }),
            'text',
        );
    });

    it('does not treat ready+empty as processing', () => {
        assert.equal(
            kiokuTranscriptDisplayMode({
                transcriptionEnabled: true,
                transcriptionStatus: 'ready',
                transcriptText: '',
            }),
            'empty_ready',
        );
        assert.equal(
            kiokuTranscriptDisplayMode({
                transcriptionEnabled: true,
                transcriptionStatus: 'ready',
                transcriptText: null,
            }),
            'empty_ready',
        );
    });

    it('keeps not_configured / failed / processing distinct', () => {
        assert.equal(
            kiokuTranscriptDisplayMode({
                transcriptionEnabled: false,
                transcriptionStatus: 'pending',
                transcriptText: null,
            }),
            'not_configured',
        );
        assert.equal(
            kiokuTranscriptDisplayMode({
                transcriptionEnabled: true,
                transcriptionStatus: 'failed',
                transcriptText: null,
            }),
            'failed',
        );
        assert.equal(
            kiokuTranscriptDisplayMode({
                transcriptionEnabled: true,
                transcriptionStatus: 'processing',
                transcriptText: null,
            }),
            'processing',
        );
        assert.equal(
            kiokuTranscriptDisplayMode({
                transcriptionEnabled: true,
                transcriptionStatus: 'pending',
                transcriptText: null,
            }),
            'processing',
        );
    });
});
