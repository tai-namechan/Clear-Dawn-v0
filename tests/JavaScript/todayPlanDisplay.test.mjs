import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    displayDurationMinutes,
    estimatePlanMinutes,
} from '../../resources/js/lib/todayPlanDuration.mjs';

function planWithSession(session) {
    return {
        steps: [
            { target_blocks: 3, rest_seconds: 60 },
            { target_blocks: 2, rest_seconds: 30 },
        ],
        sessions: [session],
    };
}

describe('today plan duration display', () => {
    it('keeps showing planned minutes after a session starts', () => {
        const plan = planWithSession({
            status: 'in_progress',
            started_at: '2026-07-29T00:00:00+09:00',
            finished_at: null,
        });

        assert.equal(estimatePlanMinutes(plan), 13);
        assert.equal(displayDurationMinutes(plan), 13);
    });

    it('does not replace planned minutes with completed elapsed time', () => {
        const plan = planWithSession({
            status: 'completed',
            started_at: '2026-07-29T00:00:00+09:00',
            finished_at: '2026-07-29T05:00:00+09:00',
        });

        assert.equal(displayDurationMinutes(plan), 13);
    });
});
