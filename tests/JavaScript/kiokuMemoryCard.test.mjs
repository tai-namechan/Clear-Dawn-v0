import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    isKiokuMemoryCardEnriching,
    isKiokuMemoryCardNavigable,
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
    it('tracks enrichment chrome independently of navigability', () => {
        assert.equal(isKiokuMemoryCardEnriching('captured'), true);
        assert.equal(isKiokuMemoryCardEnriching('enriching'), true);
        assert.equal(isKiokuMemoryCardEnriching('ready'), false);
        assert.equal(isKiokuMemoryCardEnriching('failed'), false);
    });
});
