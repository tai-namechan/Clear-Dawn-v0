/**
 * Pure logic for the Vite asset budget checker.
 *
 * The budget guards the *static closure* of each configured entry: the set of
 * chunks reachable from the entry by following `imports` only. Chunks behind
 * `dynamicImports` load on demand, so they may grow freely with the number of
 * pages without affecting any page's initial load.
 */

/**
 * Normalizes a manifest key or entry name so Windows and Unix spellings of
 * the same source path compare equal.
 *
 * @param {string} key
 * @returns {string}
 */
export function normalizeManifestKey(key) {
    return key.replaceAll('\\', '/');
}

/**
 * Finds the manifest key for an entry, tolerating path-separator differences.
 *
 * @param {Record<string, object>} manifest
 * @param {string} entryName
 * @returns {string}
 */
export function resolveEntryKey(manifest, entryName) {
    const wanted = normalizeManifestKey(entryName);

    for (const key of Object.keys(manifest)) {
        if (normalizeManifestKey(key) === wanted) {
            return key;
        }
    }

    throw new Error(
        `Entry "${entryName}" not found in the Vite manifest. ` +
            'Check the "entries" keys in the asset budget config against vite.config.ts inputs.',
    );
}

/**
 * Collects the static import closure of an entry: the entry chunk plus every
 * chunk reachable through `imports`, each counted once. `dynamicImports` are
 * intentionally not followed.
 *
 * @param {Record<string, object>} manifest
 * @param {string} entryKey
 * @returns {string[]} manifest keys, entry first
 */
export function collectStaticClosure(manifest, entryKey) {
    const resolvedEntryKey = resolveEntryKey(manifest, entryKey);
    const seen = new Set([resolvedEntryKey]);
    const queue = [resolvedEntryKey];

    while (queue.length > 0) {
        const key = queue.shift();
        const chunk = manifest[key];

        if (typeof chunk !== 'object' || chunk === null) {
            throw new Error(`Manifest chunk "${key}" is not an object.`);
        }

        for (const imported of chunk.imports ?? []) {
            if (!(imported in manifest)) {
                throw new Error(
                    `Manifest chunk "${key}" imports "${imported}", which is missing from the manifest. ` +
                        'The build output looks corrupt; rerun "npm run build".',
                );
            }

            if (!seen.has(imported)) {
                seen.add(imported);
                queue.push(imported);
            }
        }
    }

    return [...seen];
}

/**
 * Evaluates one entry's static closure against its budget.
 *
 * @param {object} params
 * @param {Record<string, object>} params.manifest
 * @param {string} params.entryName manifest key of the entry (path separators normalized)
 * @param {{maxStaticChunks?: number, maxStaticBytes?: number, maxGzipBytes?: number, forbiddenChunkPatterns?: string[]}} params.budget
 * @param {(file: string) => {bytes: number, gzipBytes: number}} params.measureChunk
 *        Measures the on-disk chunk for a manifest `file` value.
 * @returns {{
 *     entryName: string,
 *     chunkCount: number,
 *     totalBytes: number,
 *     totalGzipBytes: number,
 *     chunks: Array<{key: string, file: string, bytes: number, gzipBytes: number}>,
 *     violations: string[],
 * }}
 */
export function evaluateEntryBudget({ manifest, entryName, budget, measureChunk }) {
    const closureKeys = collectStaticClosure(manifest, entryName);
    const chunks = closureKeys.map((key) => {
        const { file } = manifest[key];

        if (typeof file !== 'string') {
            throw new Error(`Manifest chunk "${key}" has no "file" field.`);
        }

        const { bytes, gzipBytes } = measureChunk(file);

        return { key, file, bytes, gzipBytes };
    });

    const totalBytes = chunks.reduce((sum, chunk) => sum + chunk.bytes, 0);
    const totalGzipBytes = chunks.reduce((sum, chunk) => sum + chunk.gzipBytes, 0);
    const violations = [];

    if (budget.maxStaticChunks !== undefined && chunks.length > budget.maxStaticChunks) {
        violations.push(
            `static chunk count ${chunks.length} exceeds budget ${budget.maxStaticChunks}`,
        );
    }

    if (budget.maxStaticBytes !== undefined && totalBytes > budget.maxStaticBytes) {
        violations.push(
            `static closure size ${totalBytes} bytes exceeds budget ${budget.maxStaticBytes}`,
        );
    }

    if (budget.maxGzipBytes !== undefined && totalGzipBytes > budget.maxGzipBytes) {
        violations.push(
            `static closure gzip size ${totalGzipBytes} bytes exceeds budget ${budget.maxGzipBytes}`,
        );
    }

    for (const pattern of budget.forbiddenChunkPatterns ?? []) {
        const matcher = new RegExp(pattern, 'i');

        for (const { key } of chunks) {
            if (matcher.test(normalizeManifestKey(key))) {
                violations.push(
                    `chunk "${key}" matches forbidden pattern "${pattern}"; ` +
                        'product- or page-specific code must stay behind a dynamic import',
                );
            }
        }
    }

    return {
        entryName,
        chunkCount: chunks.length,
        totalBytes,
        totalGzipBytes,
        chunks: [...chunks].sort((a, b) => b.bytes - a.bytes),
        violations,
    };
}

/**
 * Evaluates every entry in the budget config.
 *
 * @param {object} params
 * @param {Record<string, object>} params.manifest
 * @param {{entries: Record<string, object>}} params.config
 * @param {(file: string) => {bytes: number, gzipBytes: number}} params.measureChunk
 * @returns {{results: Array<ReturnType<typeof evaluateEntryBudget>>, passed: boolean}}
 */
export function evaluateBudgetConfig({ manifest, config, measureChunk }) {
    const entries = Object.entries(config.entries ?? {});

    if (entries.length === 0) {
        throw new Error('Asset budget config has no "entries"; nothing to check.');
    }

    const results = entries.map(([entryName, budget]) =>
        evaluateEntryBudget({ manifest, entryName, budget, measureChunk }),
    );

    return {
        results,
        passed: results.every((result) => result.violations.length === 0),
    };
}
