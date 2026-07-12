import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    buildCaptureQueueItem,
    createCaptureQueueEngine,
    isAuthFailure,
    isPermanentCaptureRejection,
} from '../../resources/js/lib/kiokuCaptureQueue.mjs';

function createFakeStorage(initialItems = []) {
    const map = new Map(
        initialItems.map((item) => [item.clientCaptureId, item]),
    );

    return {
        map,
        failNextPut: false,
        async all() {
            return [...map.values()];
        },
        async put(item) {
            if (this.failNextPut) {
                this.failNextPut = false;

                throw new Error('quota exceeded');
            }

            map.set(item.clientCaptureId, item);
        },
        async remove(clientCaptureId) {
            map.delete(clientCaptureId);
        },
    };
}

function manualItem(id, rawContent = '一文') {
    return buildCaptureQueueItem(
        {
            clientCaptureId: id,
            sourceType: 'manual',
            rawContent,
            capturedAt: '2026-07-12T23:00:00.000Z',
        },
        () => 1_000,
    );
}

function httpError(status) {
    const error = new Error(`http ${status}`);
    error.status = status;

    return error;
}

describe('buildCaptureQueueItem', () => {
    it('fills durability-related defaults', () => {
        const item = manualItem('id-1', '原文');

        assert.equal(item.rawContent, '原文');
        assert.equal(item.audioBlob, null);
        assert.equal(item.retryCount, 0);
        assert.equal(item.rejected, false);
        assert.equal(item.enqueuedAtMs, 1_000);
    });

    it('keeps voice blob and metadata', () => {
        const blob = new Blob(['audio'], { type: 'audio/webm' });
        const item = buildCaptureQueueItem({
            clientCaptureId: 'v-1',
            sourceType: 'voice',
            audioBlob: blob,
            audioMimeType: 'audio/webm',
            durationMs: 12_000,
            capturedAt: '2026-07-12T23:00:00.000Z',
        });

        assert.equal(item.audioBlob, blob);
        assert.equal(item.audioMimeType, 'audio/webm');
        assert.equal(item.durationMs, 12_000);
        assert.equal(item.rawContent, null);
    });
});

describe('rejection classification', () => {
    it('marks 422 and 413 as permanent', () => {
        assert.equal(isPermanentCaptureRejection(422), true);
        assert.equal(isPermanentCaptureRejection(413), true);
        assert.equal(isPermanentCaptureRejection(500), false);
        assert.equal(isPermanentCaptureRejection(null), false);
    });

    it('marks 401 and 419 as auth failures', () => {
        assert.equal(isAuthFailure(401), true);
        assert.equal(isAuthFailure(419), true);
        assert.equal(isAuthFailure(429), false);
    });
});

describe('capture queue engine', () => {
    it('persists to storage before exposing the item', async () => {
        const storage = createFakeStorage();
        const engine = createCaptureQueueEngine({
            storage,
            sendCapture: async () => ({ memoryId: 'm1', created: true }),
        });

        await engine.init();
        await engine.enqueue(manualItem('id-1'));

        assert.equal(storage.map.size, 1);
        assert.equal(engine.getItems().length, 1);
    });

    it('propagates storage failure so callers never claim "saved"', async () => {
        const storage = createFakeStorage();
        storage.failNextPut = true;
        const engine = createCaptureQueueEngine({
            storage,
            sendCapture: async () => ({ memoryId: 'm1', created: true }),
        });

        await engine.init();

        await assert.rejects(() => engine.enqueue(manualItem('id-1')));
        assert.equal(engine.getItems().length, 0);
        assert.equal(storage.map.size, 0);
    });

    it('restores persisted items on init, as after a reload', async () => {
        const storage = createFakeStorage([manualItem('survivor')]);
        const engine = createCaptureQueueEngine({
            storage,
            sendCapture: async () => ({ memoryId: 'm1', created: true }),
        });

        await engine.init();

        assert.equal(engine.getItems().length, 1);
        assert.equal(engine.getItems()[0].clientCaptureId, 'survivor');
    });

    it('removes an item only after the server acknowledged it', async () => {
        const storage = createFakeStorage();
        const synced = [];
        const engine = createCaptureQueueEngine({
            storage,
            sendCapture: async (item) => ({
                memoryId: `memory-${item.clientCaptureId}`,
                created: true,
            }),
            onItemSynced: (item, result) => synced.push([item, result]),
        });

        await engine.init();
        await engine.enqueue(manualItem('id-1'));

        const result = await engine.flush();

        assert.deepEqual(result, { synced: 1, failed: 0, skipped: false });
        assert.equal(storage.map.size, 0);
        assert.equal(engine.getItems().length, 0);
        assert.equal(synced.length, 1);
        assert.equal(synced[0][1].memoryId, 'memory-id-1');
    });

    it('keeps failed items with retry metadata', async () => {
        const storage = createFakeStorage();
        const failures = [];
        const engine = createCaptureQueueEngine({
            storage,
            sendCapture: async () => {
                throw httpError(500);
            },
            onItemSyncFailed: (item, error, willRetry) =>
                failures.push(willRetry),
        });

        await engine.init();
        await engine.enqueue(manualItem('id-1'));
        await engine.flush();

        const [item] = engine.getItems();
        assert.equal(item.retryCount, 1);
        assert.equal(item.lastError, 'http_500');
        assert.equal(item.rejected, false);
        assert.equal(storage.map.get('id-1').retryCount, 1);
        assert.deepEqual(failures, [true]);
    });

    it('does not retry permanently rejected payloads', async () => {
        const storage = createFakeStorage();
        let calls = 0;
        const engine = createCaptureQueueEngine({
            storage,
            sendCapture: async () => {
                calls += 1;

                throw httpError(422);
            },
        });

        await engine.init();
        await engine.enqueue(manualItem('id-1'));
        await engine.flush();
        await engine.flush();

        assert.equal(calls, 1);
        const [item] = engine.getItems();
        assert.equal(item.rejected, true);
        // Raw is never dropped, even when the server refuses it.
        assert.equal(storage.map.size, 1);
    });

    it('stops the whole flush on auth failure and keeps every item', async () => {
        const storage = createFakeStorage();
        let calls = 0;
        const engine = createCaptureQueueEngine({
            storage,
            sendCapture: async () => {
                calls += 1;

                throw httpError(401);
            },
        });

        await engine.init();
        await engine.enqueue(manualItem('id-1'));
        await engine.enqueue(manualItem('id-2'));
        await engine.flush();

        assert.equal(calls, 1);
        assert.equal(engine.getItems().length, 2);
        assert.equal(storage.map.size, 2);
    });

    it('prevents concurrent flushes from double-sending', async () => {
        const storage = createFakeStorage();
        let calls = 0;
        let release;
        const gate = new Promise((resolve) => {
            release = resolve;
        });
        const engine = createCaptureQueueEngine({
            storage,
            sendCapture: async () => {
                calls += 1;
                await gate;

                return { memoryId: 'm1', created: true };
            },
        });

        await engine.init();
        await engine.enqueue(manualItem('id-1'));

        const first = engine.flush();
        const second = await engine.flush();

        assert.equal(second.skipped, true);
        release();
        await first;

        assert.equal(calls, 1);
        assert.equal(engine.getItems().length, 0);
    });

    it('syncs each surviving item exactly once across reconnects', async () => {
        const storage = createFakeStorage();
        const sent = [];
        let failFirst = true;
        const engine = createCaptureQueueEngine({
            storage,
            sendCapture: async (item) => {
                if (failFirst) {
                    failFirst = false;

                    throw httpError(503);
                }

                sent.push(item.clientCaptureId);

                return { memoryId: `m-${item.clientCaptureId}`, created: true };
            },
        });

        await engine.init();
        await engine.enqueue(manualItem('id-1'));

        await engine.flush(); // offline-ish failure
        await engine.flush(); // back online

        assert.deepEqual(sent, ['id-1']);
        assert.equal(engine.getItems().length, 0);
    });
});
