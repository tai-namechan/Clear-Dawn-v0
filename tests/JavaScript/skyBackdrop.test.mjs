import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    SKY_EXTEND_SELECTOR,
    SKY_FALLBACK_PX,
    SKY_HEADER_OVERLAP_PX,
    SKY_HEADER_SELECTOR,
    SKY_HERO_SELECTOR,
    SKY_OVERLAP_PX,
    skyBackdropHeight,
} from '../../resources/js/lib/skyBackdrop.mjs';

describe('skyBackdropHeight', () => {
    it('reaches past the hero bottom so the next card overlaps the sky', () => {
        assert.equal(
            skyBackdropHeight({ heroBottom: 260, containerTop: 0, scrollTop: 0 }),
            260 + SKY_OVERLAP_PX,
        );
    });

    it('measures in the scroll container, independent of scroll position', () => {
        const atTop = skyBackdropHeight({ heroBottom: 300, containerTop: 40, scrollTop: 0 });
        const scrolled = skyBackdropHeight({ heroBottom: 180, containerTop: 40, scrollTop: 120 });

        assert.equal(atTop, 260 + SKY_OVERLAP_PX);
        assert.equal(scrolled, atTop);
    });

    it('rounds to whole pixels', () => {
        assert.equal(
            skyBackdropHeight({ heroBottom: 200.6, containerTop: 0.2, scrollTop: 0 }),
            Math.round(200.4 + SKY_OVERLAP_PX),
        );
    });

    it('falls back when there is no hero or the measurement is unusable', () => {
        assert.equal(skyBackdropHeight(null), SKY_FALLBACK_PX);
        assert.equal(skyBackdropHeight({ heroBottom: Number.NaN, containerTop: 0, scrollTop: 0 }), SKY_FALLBACK_PX);
        assert.equal(skyBackdropHeight({ heroBottom: 10, containerTop: 50, scrollTop: 0 }), SKY_FALLBACK_PX);
    });

    it('targets every hero variant used by Clear Dawn pages', () => {
        for (const selector of ['.cd-hero-banner', '.cd-page-tab-shell .cd-hero-stage', '.cd-hero-panel']) {
            assert.ok(SKY_HERO_SELECTOR.includes(selector), selector);
        }
    });

    it('uses a shorter overlap for header-only pages', () => {
        assert.equal(
            skyBackdropHeight({ heroBottom: 72, containerTop: 0, scrollTop: 0 }, SKY_HEADER_OVERLAP_PX),
            72 + SKY_HEADER_OVERLAP_PX,
        );
        assert.ok(SKY_HEADER_OVERLAP_PX < SKY_OVERLAP_PX);
    });

    it('exposes the header and sky-extend selectors used by the backdrop', () => {
        assert.equal(SKY_HEADER_SELECTOR, '.cd-app-header');
        assert.equal(SKY_EXTEND_SELECTOR, '[data-sky-extend]');
    });
});
