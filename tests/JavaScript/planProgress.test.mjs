import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { planStepProgress } from '../../resources/js/lib/planProgress.mjs';

describe('planStepProgress', () => {
    it('counts completed and skipped steps of the latest session', () => {
        const plan = {
            steps: [{}, {}, {}],
            sessions: [
                { started_at: '2026-11-22T08:00:00Z', steps: [{ status: 'pending' }, { status: 'pending' }] },
                {
                    started_at: '2026-11-22T10:00:00Z',
                    steps: [{ status: 'completed' }, { status: 'skipped' }, { status: 'pending' }],
                },
            ],
        };

        assert.deepEqual(planStepProgress(plan), { done: 2, total: 3, percent: 67 });
    });

    it('falls back to the plan steps before any session starts', () => {
        assert.deepEqual(planStepProgress({ steps: [{}, {}] }), { done: 0, total: 2, percent: 0 });
    });

    it('handles plans without steps', () => {
        assert.deepEqual(planStepProgress({}), { done: 0, total: 0, percent: 0 });
        assert.deepEqual(planStepProgress({ sessions: [{ steps: [] }] }), { done: 0, total: 0, percent: 0 });
    });
});
