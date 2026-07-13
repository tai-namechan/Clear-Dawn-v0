import type {
    CaptureQueueItem,
    CaptureQueueStorage,
} from '@/lib/kiokuCaptureQueue.mjs';

const DB_NAME = 'kioku-capture-queue';
const DB_VERSION = 1;
const STORE_NAME = 'captures';

export function isIndexedDbAvailable(): boolean {
    return typeof indexedDB !== 'undefined';
}

function openDatabase(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;

            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME, {
                    keyPath: 'clientCaptureId',
                });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () =>
            reject(request.error ?? new Error('IndexedDB open failed'));
        request.onblocked = () =>
            reject(new Error('IndexedDB open blocked by another tab'));
    });
}

function requestToPromise<T>(request: IDBRequest<T>): Promise<T> {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () =>
            reject(request.error ?? new Error('IndexedDB request failed'));
    });
}

/**
 * IndexedDB-backed storage for the capture queue. Audio Blobs are stored
 * as-is (structured clone). Every operation opens a short-lived transaction
 * so a crashed tab never holds the DB open.
 */
export function createIndexedDbCaptureStorage(): CaptureQueueStorage {
    let dbPromise: Promise<IDBDatabase> | null = null;

    async function db(): Promise<IDBDatabase> {
        if (dbPromise === null) {
            dbPromise = openDatabase().catch((error: unknown) => {
                dbPromise = null;

                throw error;
            });
        }

        return dbPromise;
    }

    return {
        async all(): Promise<CaptureQueueItem[]> {
            const database = await db();
            const store = database
                .transaction(STORE_NAME, 'readonly')
                .objectStore(STORE_NAME);
            const items = await requestToPromise(
                store.getAll() as IDBRequest<CaptureQueueItem[]>,
            );

            return [...items].sort((a, b) => a.enqueuedAtMs - b.enqueuedAtMs);
        },

        async put(item: CaptureQueueItem): Promise<void> {
            const database = await db();
            const store = database
                .transaction(STORE_NAME, 'readwrite')
                .objectStore(STORE_NAME);

            await requestToPromise(store.put(item));
        },

        async remove(clientCaptureId: string): Promise<void> {
            const database = await db();
            const store = database
                .transaction(STORE_NAME, 'readwrite')
                .objectStore(STORE_NAME);

            await requestToPromise(store.delete(clientCaptureId));
        },
    };
}
