import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    areAllWatchedIdsTerminal,
    createKiokuStatusPollEngine,
    kiokuStatusPollIntervalMs,
    parseRetryAfterMs,
    pendingMemoryIds,
    samePendingIdSet,
    shouldStopKiokuStatusPoll,
} from '../../resources/js/lib/kiokuStatusPoll.mjs';

describe('kiokuStatusPoll schedule', () => {
    it('uses 3s then 5s then 8s backoff', () => {
        assert.equal(kiokuStatusPollIntervalMs(0), 3_000);
        assert.equal(kiokuStatusPollIntervalMs(29_999), 3_000);
        assert.equal(kiokuStatusPollIntervalMs(30_000), 5_000);
        assert.equal(kiokuStatusPollIntervalMs(89_999), 5_000);
        assert.equal(kiokuStatusPollIntervalMs(90_000), 8_000);
        assert.equal(kiokuStatusPollIntervalMs(179_999), 8_000);
    });

    it('stops after 3 minutes', () => {
        assert.equal(shouldStopKiokuStatusPoll(179_999), false);
        assert.equal(shouldStopKiokuStatusPoll(180_000), true);
    });

    it('collects only pending ids up to 50', () => {
        const ids = pendingMemoryIds([
            { id: 'a', status: 'captured' },
            { id: 'b', status: 'ready' },
            { id: 'c', status: 'enriching' },
            { id: 'd', status: 'failed' },
        ]);

        assert.deepEqual(ids, ['a', 'c']);
    });

    it('treats missing as terminal', () => {
        assert.equal(
            areAllWatchedIdsTerminal(['a', 'b'], { a: 'ready' }, ['b']),
            true,
        );
        assert.equal(
            areAllWatchedIdsTerminal(
                ['a', 'b'],
                { a: 'ready', b: 'enriching' },
                [],
            ),
            false,
        );
        assert.equal(
            areAllWatchedIdsTerminal(['a'], { a: 'failed' }, []),
            true,
        );
    });

    it('compares pending id sets without regard to order', () => {
        assert.equal(samePendingIdSet(['a', 'b'], ['b', 'a']), true);
        assert.equal(samePendingIdSet(['a', 'b'], ['a', 'c']), false);
    });

    it('parses Retry-After seconds and http-date', () => {
        assert.equal(parseRetryAfterMs('5'), 5_000);
        assert.equal(parseRetryAfterMs(3), 3_000);

        const now = () => Date.parse('Wed, 21 Oct 2015 07:28:00 GMT');
        assert.equal(
            parseRetryAfterMs('Wed, 21 Oct 2015 07:28:10 GMT', now),
            10_000,
        );
        assert.equal(parseRetryAfterMs('not-a-value'), null);
    });
});

describe('kiokuStatusPollEngine', () => {
    it('does not communicate when there are no pending ids', async () => {
        let fetches = 0;
        let reloads = 0;

        const engine = createKiokuStatusPollEngine({
            fetchStatus: async () => {
                fetches += 1;

                return { data: {}, missing_ids: [] };
            },
            onReload: () => {
                reloads += 1;
            },
            onSchedule: () => {},
            onClearSchedule: () => {},
        });

        engine.start([]);
        await engine.tick();

        assert.equal(fetches, 0);
        assert.equal(reloads, 0);
        assert.equal(engine.isStopped(), true);
    });

    it('reloads only once when statuses become terminal', async () => {
        let fetches = 0;
        let reloads = 0;
        const scheduled = [];

        const engine = createKiokuStatusPollEngine({
            fetchStatus: async (ids) => {
                fetches += 1;

                if (fetches === 1) {
                    return {
                        data: { [ids[0]]: 'enriching' },
                        missing_ids: [],
                    };
                }

                return {
                    data: { [ids[0]]: 'ready' },
                    missing_ids: [],
                };
            },
            onReload: () => {
                reloads += 1;
            },
            onSchedule: (delay) => {
                scheduled.push(delay);
            },
            onClearSchedule: () => {},
        });

        engine.start(['01READYTEST000000000000000']);
        await Promise.resolve();
        assert.equal(fetches, 1);
        assert.equal(reloads, 0);
        assert.deepEqual(scheduled, [3_000]);

        await engine.tick();
        assert.equal(fetches, 2);
        assert.equal(reloads, 1);

        await engine.tick();
        await engine.tick();
        assert.equal(fetches, 2);
        assert.equal(reloads, 1);
    });

    it('treats failed as terminal and reloads once', async () => {
        let reloads = 0;

        const engine = createKiokuStatusPollEngine({
            fetchStatus: async (ids) => ({
                data: { [ids[0]]: 'failed' },
                missing_ids: [],
            }),
            onReload: () => {
                reloads += 1;
            },
            onSchedule: () => {},
            onClearSchedule: () => {},
        });

        engine.start(['01FAILTEST0000000000000000']);
        await Promise.resolve();
        assert.equal(reloads, 1);

        await engine.tick();
        assert.equal(reloads, 1);
    });

    it('skips overlapping requests while in flight', async () => {
        let fetches = 0;
        let release;
        const gate = new Promise((resolve) => {
            release = resolve;
        });

        const engine = createKiokuStatusPollEngine({
            fetchStatus: async (ids) => {
                fetches += 1;
                await gate;

                return {
                    data: { [ids[0]]: 'enriching' },
                    missing_ids: [],
                };
            },
            onReload: () => {},
            onSchedule: () => {},
            onClearSchedule: () => {},
        });

        engine.start(['01INFLIGHT0000000000000000']);
        const first = engine.tick();
        const second = engine.tick();
        assert.equal(engine.isInFlight(), true);
        assert.equal(fetches, 1);

        release();
        await Promise.all([first, second]);
        assert.equal(fetches, 1);
    });

    it('pauses while hidden and checks immediately on visible', async () => {
        let hidden = false;
        let fetches = 0;
        const scheduled = [];

        const engine = createKiokuStatusPollEngine({
            isDocumentHidden: () => hidden,
            fetchStatus: async (ids) => {
                fetches += 1;

                return {
                    data: { [ids[0]]: 'enriching' },
                    missing_ids: [],
                };
            },
            onReload: () => {},
            onSchedule: (delay) => {
                scheduled.push(delay);
            },
            onClearSchedule: () => {
                scheduled.length = 0;
            },
        });

        engine.start(['01HIDDEN000000000000000000']);
        await Promise.resolve();
        assert.equal(fetches, 1);
        assert.equal(scheduled.length, 1);

        hidden = true;
        engine.onHidden();
        assert.equal(scheduled.length, 0);

        await engine.tick();
        assert.equal(fetches, 1);

        hidden = false;
        engine.onVisible();
        await Promise.resolve();
        assert.equal(fetches, 2);
    });

    it('cleans up in-flight request on dispose', async () => {
        let aborted = false;
        let release;
        const gate = new Promise((resolve) => {
            release = resolve;
        });

        const engine = createKiokuStatusPollEngine({
            fetchStatus: async (_ids, signal) => {
                signal.addEventListener('abort', () => {
                    aborted = true;
                });
                await gate;

                if (signal.aborted) {
                    throw new DOMException('Aborted', 'AbortError');
                }

                return { data: {}, missing_ids: [] };
            },
            onReload: () => {},
            onSchedule: () => {},
            onClearSchedule: () => {},
        });

        engine.start(['01DISPOSE00000000000000000']);
        await Promise.resolve();
        engine.dispose();
        release();
        await Promise.resolve();

        assert.equal(aborted, true);
        assert.equal(engine.isStopped(), true);
        assert.equal(engine.isDisposed(), true);
    });

    it('stops after 3 minutes without reload', async () => {
        let now = 1_000;
        let fetches = 0;
        let reloads = 0;

        const engine = createKiokuStatusPollEngine({
            now: () => now,
            fetchStatus: async (ids) => {
                fetches += 1;

                return {
                    data: { [ids[0]]: 'enriching' },
                    missing_ids: [],
                };
            },
            onReload: () => {
                reloads += 1;
            },
            onSchedule: () => {},
            onClearSchedule: () => {},
        });

        engine.start(['01TIMEOUT00000000000000000']);
        await Promise.resolve();
        assert.equal(fetches, 1);

        now = 1_000 + 180_000;
        await engine.tick();

        assert.equal(engine.isTimedOut(), true);
        assert.equal(reloads, 0);
        assert.equal(fetches, 1);
    });

    it('preserves filter query contract via reload callback only', async () => {
        const reloadCalls = [];

        const engine = createKiokuStatusPollEngine({
            fetchStatus: async (ids) => ({
                data: { [ids[0]]: 'ready' },
                missing_ids: [],
            }),
            onReload: () => {
                reloadCalls.push({ preserveUrl: true });
            },
            onSchedule: () => {},
            onClearSchedule: () => {},
        });

        engine.start(['01FILTER000000000000000000']);
        await Promise.resolve();

        assert.deepEqual(reloadCalls, [{ preserveUrl: true }]);
    });

    it('respects Retry-After on 429 without counting as consecutive failure', async () => {
        let now = 0;
        let fetches = 0;
        const scheduled = [];
        let clears = 0;

        const engine = createKiokuStatusPollEngine({
            now: () => now,
            fetchStatus: async (ids) => {
                fetches += 1;

                if (fetches === 1) {
                    const error = new Error('Too Many Requests');
                    error.status = 429;
                    error.retryAfterSeconds = 7;

                    throw error;
                }

                return {
                    data: { [ids[0]]: 'enriching' },
                    missing_ids: [],
                };
            },
            onReload: () => {},
            onSchedule: (delay) => {
                scheduled.push(delay);
            },
            onClearSchedule: () => {
                clears += 1;
            },
        });

        engine.start(['01RATELIMIT000000000000000']);
        await Promise.resolve();

        assert.equal(fetches, 1);
        assert.equal(engine.getConsecutiveFailures(), 0);
        assert.deepEqual(scheduled, [7_000]);

        now = 7_000;
        await engine.tick();

        assert.equal(fetches, 2);
        assert.equal(engine.getConsecutiveFailures(), 0);
        assert.equal(engine.isTimedOut(), false);
        assert.ok(clears >= 1);
    });

    it('does not extend the 180s deadline while waiting after 429', async () => {
        let now = 0;
        let fetches = 0;
        const scheduled = [];

        const engine = createKiokuStatusPollEngine({
            now: () => now,
            fetchStatus: async () => {
                fetches += 1;
                const error = new Error('Too Many Requests');
                error.status = 429;
                error.retryAfterSeconds = 60;

                throw error;
            },
            onReload: () => {},
            onSchedule: (delay) => {
                scheduled.push(delay);
            },
            onClearSchedule: () => {},
        });

        engine.start(['01DEADLINE4290000000000000']);
        await Promise.resolve();
        assert.equal(fetches, 1);
        assert.deepEqual(scheduled, [60_000]);

        now = 170_000;
        await engine.tick();

        assert.equal(fetches, 2);
        assert.equal(engine.isTimedOut(), true);
        assert.equal(engine.getConsecutiveFailures(), 0);
    });

    it('resets consecutive failures after one successful response', async () => {
        let fetches = 0;

        const engine = createKiokuStatusPollEngine({
            fetchStatus: async (ids) => {
                fetches += 1;

                if (fetches <= 4) {
                    throw Object.assign(new Error('server'), { status: 500 });
                }

                if (fetches === 5) {
                    return {
                        data: { [ids[0]]: 'enriching' },
                        missing_ids: [],
                    };
                }

                if (fetches <= 9) {
                    throw Object.assign(new Error('server'), { status: 500 });
                }

                return {
                    data: { [ids[0]]: 'ready' },
                    missing_ids: [],
                };
            },
            onReload: () => {},
            onSchedule: () => {},
            onClearSchedule: () => {},
        });

        engine.start(['01FAILRESET000000000000000']);
        await Promise.resolve();
        assert.equal(engine.getConsecutiveFailures(), 1);

        for (let i = 0; i < 3; i += 1) {
            await engine.tick();
        }

        assert.equal(engine.getConsecutiveFailures(), 4);
        assert.equal(engine.isTimedOut(), false);

        await engine.tick();
        assert.equal(engine.getConsecutiveFailures(), 0);

        for (let i = 0; i < 4; i += 1) {
            await engine.tick();
        }

        assert.equal(engine.getConsecutiveFailures(), 4);
        assert.equal(engine.isTimedOut(), false);
        assert.equal(engine.isStopped(), false);
    });

    it('restarts when pending id set changes and aborts in-flight request', async () => {
        let fetches = 0;
        /** @type {string[][]} */
        const watched = [];
        let aborted = false;
        let release;
        const gate = new Promise((resolve) => {
            release = resolve;
        });
        let clears = 0;

        const engine = createKiokuStatusPollEngine({
            fetchStatus: async (ids, signal) => {
                fetches += 1;
                watched.push([...ids]);

                if (fetches === 1) {
                    signal.addEventListener('abort', () => {
                        aborted = true;
                    });
                    await gate;

                    if (signal.aborted) {
                        throw new DOMException('Aborted', 'AbortError');
                    }
                }

                return {
                    data: Object.fromEntries(
                        ids.map((id) => [id, 'enriching']),
                    ),
                    missing_ids: [],
                };
            },
            onReload: () => {},
            onSchedule: () => {},
            onClearSchedule: () => {
                clears += 1;
            },
        });

        engine.start(['01A00000000000000000000000']);
        await Promise.resolve();
        assert.equal(fetches, 1);

        engine.setPendingIds([
            '01B00000000000000000000000',
            '01C00000000000000000000000',
        ]);
        assert.equal(aborted, true);
        assert.ok(clears >= 1);

        release();
        await Promise.resolve();
        await Promise.resolve();

        assert.equal(fetches, 2);
        assert.deepEqual(watched[1], [
            '01B00000000000000000000000',
            '01C00000000000000000000000',
        ]);
    });

    it('does not restart for the same id set in a different order', async () => {
        let fetches = 0;

        const engine = createKiokuStatusPollEngine({
            fetchStatus: async (ids) => {
                fetches += 1;

                return {
                    data: Object.fromEntries(
                        ids.map((id) => [id, 'enriching']),
                    ),
                    missing_ids: [],
                };
            },
            onReload: () => {},
            onSchedule: () => {},
            onClearSchedule: () => {},
        });

        engine.start([
            '01A00000000000000000000000',
            '01B00000000000000000000000',
        ]);
        await Promise.resolve();
        assert.equal(fetches, 1);

        engine.setPendingIds([
            '01B00000000000000000000000',
            '01A00000000000000000000000',
        ]);
        await Promise.resolve();

        assert.equal(fetches, 1);
    });

    it('stops when pending becomes empty', async () => {
        let fetches = 0;

        const engine = createKiokuStatusPollEngine({
            fetchStatus: async (ids) => {
                fetches += 1;

                return {
                    data: { [ids[0]]: 'enriching' },
                    missing_ids: [],
                };
            },
            onReload: () => {},
            onSchedule: () => {},
            onClearSchedule: () => {},
        });

        engine.start(['01EMPTYSTOP000000000000000']);
        await Promise.resolve();
        assert.equal(fetches, 1);

        engine.setPendingIds([]);
        assert.equal(engine.isStopped(), true);

        await engine.tick();
        assert.equal(fetches, 1);
    });

    it('does not restart after dispose', async () => {
        let fetches = 0;

        const engine = createKiokuStatusPollEngine({
            fetchStatus: async (ids) => {
                fetches += 1;

                return {
                    data: { [ids[0]]: 'enriching' },
                    missing_ids: [],
                };
            },
            onReload: () => {},
            onSchedule: () => {},
            onClearSchedule: () => {},
        });

        engine.start(['01AFTERDISPOSE000000000000']);
        await Promise.resolve();
        assert.equal(fetches, 1);

        engine.dispose();
        engine.setPendingIds(['01NEWAFTERDISPOSE000000000']);
        engine.start(['01NEWAFTERDISPOSE000000000']);
        await engine.tick();

        assert.equal(fetches, 1);
        assert.equal(engine.isDisposed(), true);
    });

    it('ignores stale A success after switching to B', async () => {
        /** @type {{ resolve: (v: unknown) => void, reject: (e: unknown) => void }[]} */
        const deferred = [];
        let fetches = 0;
        let reloads = 0;
        const scheduled = [];
        let timedOutEvents = 0;

        const engine = createKiokuStatusPollEngine({
            fetchStatus: () =>
                new Promise((resolve, reject) => {
                    fetches += 1;
                    deferred.push({ resolve, reject });
                }),
            onReload: () => {
                reloads += 1;
            },
            onSchedule: (delay) => {
                scheduled.push(delay);
            },
            onClearSchedule: () => {},
            onTimedOutChange: (value) => {
                if (value) {
                    timedOutEvents += 1;
                }
            },
        });

        engine.start(['01A00000000000000000000000']);
        await Promise.resolve();
        assert.equal(fetches, 1);
        assert.equal(engine.isInFlight(), true);

        engine.setPendingIds(['01B00000000000000000000000']);
        await Promise.resolve();
        assert.equal(fetches, 2);
        assert.equal(engine.isInFlight(), true);
        const runAfterB = engine.getRunId();

        // Stale A resolves as terminal — must not reload or clear B.
        deferred[0].resolve({
            data: { '01A00000000000000000000000': 'ready' },
            missing_ids: [],
        });
        await Promise.resolve();
        await Promise.resolve();

        assert.equal(reloads, 0);
        assert.equal(timedOutEvents, 0);
        assert.equal(engine.getConsecutiveFailures(), 0);
        assert.equal(engine.isInFlight(), true);
        assert.equal(engine.getRunId(), runAfterB);
        assert.equal(scheduled.length, 0);

        deferred[1].resolve({
            data: { '01B00000000000000000000000': 'enriching' },
            missing_ids: [],
        });
        await Promise.resolve();
        await Promise.resolve();

        assert.equal(reloads, 0);
        assert.equal(engine.isInFlight(), false);
        assert.deepEqual(scheduled, [3_000]);
    });

    it('ignores stale A failure after switching to B', async () => {
        /** @type {{ resolve: (v: unknown) => void, reject: (e: unknown) => void }[]} */
        const deferred = [];
        let fetches = 0;
        let reloads = 0;
        const scheduled = [];

        const engine = createKiokuStatusPollEngine({
            fetchStatus: () =>
                new Promise((resolve, reject) => {
                    fetches += 1;
                    deferred.push({ resolve, reject });
                }),
            onReload: () => {
                reloads += 1;
            },
            onSchedule: (delay) => {
                scheduled.push(delay);
            },
            onClearSchedule: () => {},
        });

        engine.start(['01A00000000000000000000000']);
        await Promise.resolve();
        engine.setPendingIds(['01B00000000000000000000000']);
        await Promise.resolve();
        assert.equal(fetches, 2);

        deferred[0].reject(Object.assign(new Error('boom'), { status: 500 }));
        await Promise.resolve();
        await Promise.resolve();

        assert.equal(engine.getConsecutiveFailures(), 0);
        assert.equal(engine.isTimedOut(), false);
        assert.equal(reloads, 0);
        assert.equal(engine.isInFlight(), true);
        assert.equal(scheduled.length, 0);

        deferred[1].resolve({
            data: { '01B00000000000000000000000': 'enriching' },
            missing_ids: [],
        });
        await Promise.resolve();
        await Promise.resolve();

        assert.equal(engine.getConsecutiveFailures(), 0);
        assert.equal(engine.isInFlight(), false);
        assert.deepEqual(scheduled, [3_000]);
    });

    it('stale A finally does not clear B inFlight or AbortController ownership', async () => {
        /** @type {{ resolve: (v: unknown) => void, reject: (e: unknown) => void }[]} */
        const deferred = [];
        /** @type {AbortSignal[]} */
        const signals = [];

        const engine = createKiokuStatusPollEngine({
            fetchStatus: (_ids, signal) =>
                new Promise((resolve, reject) => {
                    signals.push(signal);
                    deferred.push({ resolve, reject });
                }),
            onReload: () => {},
            onSchedule: () => {},
            onClearSchedule: () => {},
        });

        engine.start(['01A00000000000000000000000']);
        await Promise.resolve();
        assert.equal(engine.isInFlight(), true);

        engine.setPendingIds(['01B00000000000000000000000']);
        await Promise.resolve();
        assert.equal(engine.isInFlight(), true);
        assert.equal(signals[0].aborted, true);
        assert.equal(signals[1].aborted, false);

        deferred[0].resolve({
            data: { '01A00000000000000000000000': 'enriching' },
            missing_ids: [],
        });
        await Promise.resolve();
        await Promise.resolve();

        assert.equal(engine.isInFlight(), true);
        assert.equal(signals[1].aborted, false);
    });

    it('stale A terminal response does not trigger reload', async () => {
        /** @type {{ resolve: (v: unknown) => void, reject: (e: unknown) => void }[]} */
        const deferred = [];
        let reloads = 0;

        const engine = createKiokuStatusPollEngine({
            fetchStatus: () =>
                new Promise((resolve, reject) => {
                    deferred.push({ resolve, reject });
                }),
            onReload: () => {
                reloads += 1;
            },
            onSchedule: () => {},
            onClearSchedule: () => {},
        });

        engine.start(['01A00000000000000000000000']);
        await Promise.resolve();
        engine.setPendingIds(['01B00000000000000000000000']);
        await Promise.resolve();

        deferred[0].resolve({
            data: { '01A00000000000000000000000': 'ready' },
            missing_ids: [],
        });
        await Promise.resolve();
        await Promise.resolve();

        assert.equal(reloads, 0);
        assert.equal(engine.didReload(), false);

        deferred[1].resolve({
            data: { '01B00000000000000000000000': 'ready' },
            missing_ids: [],
        });
        await Promise.resolve();
        await Promise.resolve();

        assert.equal(reloads, 1);
        assert.equal(engine.didReload(), true);
    });

    it('dispose ignores late completion without callback/timer/reload', async () => {
        /** @type {{ resolve: (v: unknown) => void, reject: (e: unknown) => void }[]} */
        const deferred = [];
        let reloads = 0;
        let schedules = 0;
        let timedOutEvents = 0;

        const engine = createKiokuStatusPollEngine({
            fetchStatus: () =>
                new Promise((resolve, reject) => {
                    deferred.push({ resolve, reject });
                }),
            onReload: () => {
                reloads += 1;
            },
            onSchedule: () => {
                schedules += 1;
            },
            onClearSchedule: () => {},
            onTimedOutChange: (value) => {
                if (value) {
                    timedOutEvents += 1;
                }
            },
        });

        engine.start(['01DISPOSESTALE000000000000']);
        await Promise.resolve();
        engine.dispose();

        deferred[0].resolve({
            data: { '01DISPOSESTALE000000000000': 'ready' },
            missing_ids: [],
        });
        await Promise.resolve();
        await Promise.resolve();

        assert.equal(reloads, 0);
        assert.equal(schedules, 0);
        assert.equal(timedOutEvents, 0);
        assert.equal(engine.getConsecutiveFailures(), 0);
        assert.equal(engine.isInFlight(), false);
    });

    it('dispose ignores late failure without callback/timer/reload', async () => {
        /** @type {{ resolve: (v: unknown) => void, reject: (e: unknown) => void }[]} */
        const deferred = [];
        let reloads = 0;
        let schedules = 0;
        let timedOutEvents = 0;

        const engine = createKiokuStatusPollEngine({
            fetchStatus: () =>
                new Promise((resolve, reject) => {
                    deferred.push({ resolve, reject });
                }),
            onReload: () => {
                reloads += 1;
            },
            onSchedule: () => {
                schedules += 1;
            },
            onClearSchedule: () => {},
            onTimedOutChange: (value) => {
                if (value) {
                    timedOutEvents += 1;
                }
            },
        });

        engine.start(['01DISPOSEFAIL0000000000000']);
        await Promise.resolve();
        engine.dispose();

        deferred[0].reject(Object.assign(new Error('late'), { status: 500 }));
        await Promise.resolve();
        await Promise.resolve();

        assert.equal(reloads, 0);
        assert.equal(schedules, 0);
        assert.equal(timedOutEvents, 0);
        assert.equal(engine.getConsecutiveFailures(), 0);
        assert.equal(engine.isInFlight(), false);
    });

    it('stale 429 from A does not schedule a timer for B', async () => {
        /** @type {{ resolve: (v: unknown) => void, reject: (e: unknown) => void }[]} */
        const deferred = [];
        const scheduled = [];

        const engine = createKiokuStatusPollEngine({
            fetchStatus: () =>
                new Promise((resolve, reject) => {
                    deferred.push({ resolve, reject });
                }),
            onReload: () => {},
            onSchedule: (delay) => {
                scheduled.push(delay);
            },
            onClearSchedule: () => {},
        });

        engine.start(['01A00000000000000000000000']);
        await Promise.resolve();
        engine.setPendingIds(['01B00000000000000000000000']);
        await Promise.resolve();
        assert.equal(engine.isInFlight(), true);

        deferred[0].reject(
            Object.assign(new Error('Too Many Requests'), {
                status: 429,
                retryAfterSeconds: 9,
            }),
        );
        await Promise.resolve();
        await Promise.resolve();

        assert.deepEqual(scheduled, []);
        assert.equal(engine.getConsecutiveFailures(), 0);
        assert.equal(engine.isInFlight(), true);
        assert.equal(engine.isTimedOut(), false);

        deferred[1].resolve({
            data: { '01B00000000000000000000000': 'enriching' },
            missing_ids: [],
        });
        await Promise.resolve();
        await Promise.resolve();

        assert.deepEqual(scheduled, [3_000]);
    });
});
