import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    kcalAchievement,
    kcalAchievementBadge,
} from '../../resources/js/lib/kcalAchievement.mjs';

describe('kcalAchievement', () => {
    it('shows the real percentage but caps the ring at 100 when over the goal', () => {
        const result = kcalAchievement(2715, 2550);

        assert.equal(result.percent, 106);
        assert.equal(result.ringPercent, 100);
        assert.equal(result.difference, 165);
        assert.equal(result.status, 'over');
    });

    it('reports what remains below the goal', () => {
        const result = kcalAchievement(1200, '2400');

        assert.equal(result.percent, 50);
        assert.equal(result.ringPercent, 50);
        assert.equal(result.difference, -1200);
        assert.equal(result.status, 'remaining');
    });

    it('marks an exact hit as reached', () => {
        assert.equal(kcalAchievement(2000, 2000).status, 'reached');
    });

    it('does not invent a percentage without a goal', () => {
        for (const goal of [null, undefined, 0, -10, 'abc']) {
            const result = kcalAchievement(1800, goal);

            assert.equal(result.percent, null);
            assert.equal(result.ringPercent, 0);
            assert.equal(result.difference, null);
            assert.equal(result.status, 'unset');
        }
    });

    it('treats missing intake as zero', () => {
        assert.equal(kcalAchievement(Number.NaN, 2000).percent, 0);
    });
});

describe('kcalAchievementBadge', () => {
    it('labels over, remaining, exact and unset', () => {
        assert.equal(kcalAchievementBadge(kcalAchievement(2715, 2550)), '超過 +165 kcal');
        assert.equal(kcalAchievementBadge(kcalAchievement(2230, 2550)), '残り 320 kcal');
        assert.equal(kcalAchievementBadge(kcalAchievement(2550, 2550)), '目標ちょうど');
        assert.equal(kcalAchievementBadge(kcalAchievement(2550, null)), null);
    });

    it('formats thousands', () => {
        assert.equal(kcalAchievementBadge(kcalAchievement(0, 2550)), '残り 2,550 kcal');
    });
});
