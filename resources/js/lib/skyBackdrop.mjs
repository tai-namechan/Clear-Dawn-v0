/**
 * The first element of each kind that counts as a page hero, i.e. the
 * white-on-sky title area the dawn sky must sit behind.
 */
export const SKY_HERO_SELECTOR =
    '.cd-hero-banner, .cd-page-tab-shell .cd-hero-stage, .cd-hero-panel';

/**
 * Elements that sit on the sky right under the hero (e.g. a glass summary
 * card) mark themselves with this attribute so the sky reaches past them.
 */
export const SKY_EXTEND_SELECTOR = '[data-sky-extend]';

/** Pages without a hero still keep the header on the sky. */
export const SKY_HEADER_SELECTOR = '.cd-app-header';

/** How far the sky reaches below the hero, so the next card overlaps it. */
export const SKY_OVERLAP_PX = 56;

/** Header-only pages: just enough sky under the header for a soft edge. */
export const SKY_HEADER_OVERLAP_PX = 24;

/** Used when a page has no hero (or before the first measurement). */
export const SKY_FALLBACK_PX = 288;

/**
 * Height of the sky backdrop in the scroll container's coordinate space.
 *
 * @param {{ heroBottom: number, containerTop: number, scrollTop: number } | null} measurement
 *   `heroBottom` / `containerTop` are viewport coordinates
 *   (getBoundingClientRect), `scrollTop` is the container's scroll offset.
 * @param {number} [overlap] how far the sky reaches below `heroBottom`
 * @returns {number}
 */
export function skyBackdropHeight(measurement, overlap = SKY_OVERLAP_PX) {
    if (!measurement) {
        return SKY_FALLBACK_PX;
    }

    const { heroBottom, containerTop, scrollTop } = measurement;

    if (![heroBottom, containerTop, scrollTop].every(Number.isFinite)) {
        return SKY_FALLBACK_PX;
    }

    const heroBottomInContainer = heroBottom - containerTop + scrollTop;

    if (heroBottomInContainer <= 0) {
        return SKY_FALLBACK_PX;
    }

    return Math.round(heroBottomInContainer + overlap);
}
