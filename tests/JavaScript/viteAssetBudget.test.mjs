import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    collectStaticClosure,
    evaluateBudgetConfig,
    evaluateEntryBudget,
    normalizeManifestKey,
    resolveEntryKey,
} from '../../scripts/lib/viteAssetBudget.mjs';

/**
 * app.ts statically imports shared.js, which statically imports nested.js.
 * pages/Kioku/Index.vue is only reachable through dynamicImports and pulls
 * in heavy.js statically on its own.
 */
function createFixtureManifest() {
    return {
        'resources/js/app.ts': {
            file: 'assets/app.js',
            isEntry: true,
            imports: ['_shared.js'],
            dynamicImports: ['resources/js/pages/Kioku/Index.vue'],
        },
        '_shared.js': {
            file: 'assets/shared.js',
            imports: ['_nested.js'],
        },
        '_nested.js': {
            file: 'assets/nested.js',
        },
        'resources/js/pages/Kioku/Index.vue': {
            file: 'assets/kioku-index.js',
            imports: ['_heavy.js'],
        },
        '_heavy.js': {
            file: 'assets/heavy.js',
        },
    };
}

const fixtureSizes = {
    'assets/app.js': { bytes: 100, gzipBytes: 40 },
    'assets/shared.js': { bytes: 50, gzipBytes: 20 },
    'assets/nested.js': { bytes: 25, gzipBytes: 10 },
    'assets/kioku-index.js': { bytes: 1000, gzipBytes: 400 },
    'assets/heavy.js': { bytes: 5000, gzipBytes: 2000 },
};

function measureFixtureChunk(file) {
    const size = fixtureSizes[file];

    if (!size) {
        throw new Error(`No fixture size for ${file}`);
    }

    return size;
}

describe('collectStaticClosure', () => {
    it('includes the entry and chunks reachable through imports', () => {
        const closure = collectStaticClosure(
            createFixtureManifest(),
            'resources/js/app.ts',
        );

        assert.deepEqual(
            [...closure].sort(),
            ['_nested.js', '_shared.js', 'resources/js/app.ts'],
        );
    });

    it('includes nested static imports transitively', () => {
        const closure = collectStaticClosure(
            createFixtureManifest(),
            'resources/js/app.ts',
        );

        assert.ok(closure.includes('_nested.js'));
    });

    it('excludes chunks only reachable through dynamicImports', () => {
        const closure = collectStaticClosure(
            createFixtureManifest(),
            'resources/js/app.ts',
        );

        assert.ok(!closure.includes('resources/js/pages/Kioku/Index.vue'));
        assert.ok(!closure.includes('_heavy.js'));
    });

    it('counts a chunk only once when imported from multiple places', () => {
        const manifest = createFixtureManifest();

        manifest['_shared.js'].imports = ['_nested.js', '_alsoNested.js'];
        manifest['_alsoNested.js'] = {
            file: 'assets/also-nested.js',
            imports: ['_nested.js'],
        };

        const closure = collectStaticClosure(manifest, 'resources/js/app.ts');
        const nestedOccurrences = closure.filter((key) => key === '_nested.js');

        assert.equal(nestedOccurrences.length, 1);
        assert.equal(closure.length, 4);
    });

    it('fails clearly when the entry is missing from the manifest', () => {
        assert.throws(
            () => collectStaticClosure(createFixtureManifest(), 'resources/js/missing.ts'),
            /Entry "resources\/js\/missing\.ts" not found/,
        );
    });

    it('fails clearly when the manifest references a missing chunk', () => {
        const manifest = createFixtureManifest();

        manifest['_shared.js'].imports = ['_ghost.js'];

        assert.throws(
            () => collectStaticClosure(manifest, 'resources/js/app.ts'),
            /imports "_ghost\.js", which is missing/,
        );
    });
});

describe('resolveEntryKey', () => {
    it('matches Windows-style entry names against Unix manifest keys', () => {
        assert.equal(
            resolveEntryKey(createFixtureManifest(), 'resources\\js\\app.ts'),
            'resources/js/app.ts',
        );
    });

    it('matches Unix-style entry names against Windows manifest keys', () => {
        const manifest = { 'resources\\js\\app.ts': { file: 'assets/app.js' } };

        assert.equal(
            resolveEntryKey(manifest, 'resources/js/app.ts'),
            'resources\\js\\app.ts',
        );
    });
});

describe('normalizeManifestKey', () => {
    it('converts backslashes to forward slashes', () => {
        assert.equal(
            normalizeManifestKey('resources\\js\\pages\\Yoyu\\Index.vue'),
            'resources/js/pages/Yoyu/Index.vue',
        );
    });
});

describe('evaluateEntryBudget', () => {
    const passingBudget = {
        maxStaticChunks: 3,
        maxStaticBytes: 175,
        maxGzipBytes: 70,
    };

    it('passes when the closure is within budget', () => {
        const result = evaluateEntryBudget({
            manifest: createFixtureManifest(),
            entryName: 'resources/js/app.ts',
            budget: passingBudget,
            measureChunk: measureFixtureChunk,
        });

        assert.deepEqual(result.violations, []);
        assert.equal(result.chunkCount, 3);
        assert.equal(result.totalBytes, 175);
        assert.equal(result.totalGzipBytes, 70);
    });

    it('fails when the static chunk count exceeds the budget', () => {
        const result = evaluateEntryBudget({
            manifest: createFixtureManifest(),
            entryName: 'resources/js/app.ts',
            budget: { ...passingBudget, maxStaticChunks: 2 },
            measureChunk: measureFixtureChunk,
        });

        assert.match(result.violations.join('\n'), /chunk count 3 exceeds budget 2/);
    });

    it('fails when the static bytes exceed the budget', () => {
        const result = evaluateEntryBudget({
            manifest: createFixtureManifest(),
            entryName: 'resources/js/app.ts',
            budget: { ...passingBudget, maxStaticBytes: 174 },
            measureChunk: measureFixtureChunk,
        });

        assert.match(result.violations.join('\n'), /175 bytes exceeds budget 174/);
    });

    it('fails when the gzip bytes exceed the budget', () => {
        const result = evaluateEntryBudget({
            manifest: createFixtureManifest(),
            entryName: 'resources/js/app.ts',
            budget: { ...passingBudget, maxGzipBytes: 69 },
            measureChunk: measureFixtureChunk,
        });

        assert.match(result.violations.join('\n'), /70 bytes exceeds budget 69/);
    });

    it('fails when a product chunk becomes statically reachable', () => {
        const manifest = createFixtureManifest();

        manifest['resources/js/app.ts'].imports.push(
            'resources/js/pages/Kioku/Index.vue',
        );

        const result = evaluateEntryBudget({
            manifest,
            entryName: 'resources/js/app.ts',
            budget: {
                forbiddenChunkPatterns: ['resources/js/pages/', 'kioku'],
            },
            measureChunk: measureFixtureChunk,
        });

        assert.ok(
            result.violations.some((violation) =>
                violation.includes('resources/js/pages/'),
            ),
        );
    });

    it('does not flag forbidden patterns for dynamic-only chunks', () => {
        const result = evaluateEntryBudget({
            manifest: createFixtureManifest(),
            entryName: 'resources/js/app.ts',
            budget: {
                forbiddenChunkPatterns: ['resources/js/pages/', 'kioku'],
            },
            measureChunk: measureFixtureChunk,
        });

        assert.deepEqual(result.violations, []);
    });

    it('fails clearly when a chunk has no file field', () => {
        const manifest = createFixtureManifest();

        delete manifest['_nested.js'].file;

        assert.throws(
            () =>
                evaluateEntryBudget({
                    manifest,
                    entryName: 'resources/js/app.ts',
                    budget: passingBudget,
                    measureChunk: measureFixtureChunk,
                }),
            /"_nested\.js" has no "file" field/,
        );
    });
});

describe('evaluateBudgetConfig', () => {
    it('aggregates results across entries and reports overall pass', () => {
        const { results, passed } = evaluateBudgetConfig({
            manifest: createFixtureManifest(),
            config: {
                entries: {
                    'resources/js/app.ts': { maxStaticChunks: 3 },
                },
            },
            measureChunk: measureFixtureChunk,
        });

        assert.equal(results.length, 1);
        assert.equal(passed, true);
    });

    it('reports overall failure when any entry violates its budget', () => {
        const { passed } = evaluateBudgetConfig({
            manifest: createFixtureManifest(),
            config: {
                entries: {
                    'resources/js/app.ts': { maxStaticChunks: 1 },
                },
            },
            measureChunk: measureFixtureChunk,
        });

        assert.equal(passed, false);
    });

    it('fails clearly when the config has no entries', () => {
        assert.throws(
            () =>
                evaluateBudgetConfig({
                    manifest: createFixtureManifest(),
                    config: { entries: {} },
                    measureChunk: measureFixtureChunk,
                }),
            /no "entries"/,
        );
    });
});
