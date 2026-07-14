import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    isKiokuMemoryCardEnriching,
    isKiokuMemoryCardNavigable,
    kiokuMemoryDisplayTitle,
} from '../../resources/js/lib/kiokuMemoryCard.mjs';

describe('isKiokuMemoryCardNavigable', () => {
    it('keeps voice cards openable while captured/pending (provider=none path)', () => {
        assert.equal(
            isKiokuMemoryCardNavigable({
                source_type: 'voice',
                status: 'captured',
            }),
            true,
        );
        assert.equal(
            isKiokuMemoryCardNavigable({
                source_type: 'voice',
                status: 'enriching',
            }),
            true,
        );
        assert.equal(
            isKiokuMemoryCardNavigable({
                source_type: 'voice',
                status: 'ready',
            }),
            true,
        );
        assert.equal(
            isKiokuMemoryCardNavigable({
                source_type: 'voice',
                status: 'failed',
            }),
            true,
        );
    });

    it('preserves manual/url pending cards as non-navigable', () => {
        assert.equal(
            isKiokuMemoryCardNavigable({
                source_type: 'manual',
                status: 'captured',
            }),
            false,
        );
        assert.equal(
            isKiokuMemoryCardNavigable({
                source_type: 'manual',
                status: 'enriching',
            }),
            false,
        );
        assert.equal(
            isKiokuMemoryCardNavigable({
                source_type: 'url',
                status: 'ready',
            }),
            true,
        );
        assert.equal(
            isKiokuMemoryCardNavigable({
                source_type: 'manual',
                status: 'failed',
            }),
            true,
        );
    });
});

describe('isKiokuMemoryCardEnriching', () => {
    it('does not spin forever for voice when transcription is not configured', () => {
        assert.equal(
            isKiokuMemoryCardEnriching(
                {
                    source_type: 'voice',
                    status: 'captured',
                    transcription_status: 'pending',
                },
                { transcriptionEnabled: false },
            ),
            false,
        );
    });

    it('does not treat voice transcription-pending as AI enriching', () => {
        assert.equal(
            isKiokuMemoryCardEnriching(
                {
                    source_type: 'voice',
                    status: 'captured',
                    transcription_status: 'pending',
                },
                { transcriptionEnabled: true },
            ),
            false,
        );
        assert.equal(
            isKiokuMemoryCardEnriching(
                {
                    source_type: 'voice',
                    status: 'captured',
                    transcription_status: 'processing',
                },
                { transcriptionEnabled: true },
            ),
            false,
        );
    });

    it('shows enriching chrome once voice transcript is ready or status is enriching', () => {
        assert.equal(
            isKiokuMemoryCardEnriching(
                {
                    source_type: 'voice',
                    status: 'captured',
                    transcription_status: 'ready',
                },
                { transcriptionEnabled: true },
            ),
            true,
        );
        assert.equal(
            isKiokuMemoryCardEnriching({
                source_type: 'voice',
                status: 'enriching',
                transcription_status: 'ready',
            }),
            true,
        );
    });

    it('preserves manual/url enriching behaviour', () => {
        assert.equal(
            isKiokuMemoryCardEnriching({
                source_type: 'manual',
                status: 'captured',
            }),
            true,
        );
        assert.equal(
            isKiokuMemoryCardEnriching({
                source_type: 'manual',
                status: 'enriching',
            }),
            true,
        );
        assert.equal(
            isKiokuMemoryCardEnriching({
                source_type: 'manual',
                status: 'ready',
            }),
            false,
        );
        assert.equal(
            isKiokuMemoryCardEnriching({
                source_type: 'url',
                status: 'failed',
            }),
            false,
        );
    });
});

describe('kiokuMemoryDisplayTitle', () => {
    it('replaces the voice placeholder while transcription has not finished', () => {
        assert.equal(
            kiokuMemoryDisplayTitle({
                source_type: 'voice',
                title: '整理中…',
                transcription_status: 'pending',
            }),
            '音声メモ',
        );
        assert.equal(
            kiokuMemoryDisplayTitle({
                source_type: 'voice',
                title: '整理中…',
                transcription_status: 'processing',
            }),
            '音声メモ',
        );
        assert.equal(
            kiokuMemoryDisplayTitle({
                source_type: 'voice',
                title: '整理中…',
                transcription_status: 'failed',
            }),
            '音声メモ',
        );
    });

    it('keeps 整理中… once transcript is ready and enrich may still run', () => {
        assert.equal(
            kiokuMemoryDisplayTitle({
                source_type: 'voice',
                title: '整理中…',
                transcription_status: 'ready',
            }),
            '整理中…',
        );
    });

    it('leaves manual titles alone', () => {
        assert.equal(
            kiokuMemoryDisplayTitle({
                source_type: 'manual',
                title: '整理中…',
            }),
            '整理中…',
        );
        assert.equal(
            kiokuMemoryDisplayTitle({
                source_type: 'voice',
                title: '会議メモ',
                transcription_status: 'pending',
            }),
            '会議メモ',
        );
    });
});
