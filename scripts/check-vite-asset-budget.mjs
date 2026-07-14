#!/usr/bin/env node
/**
 * Checks the production Vite build against the asset budget in
 * scripts/asset-budget.json. Run "npm run build" first, then
 * "npm run assets:check".
 *
 * Fails (exit 1) when an entry's static import closure exceeds its chunk,
 * byte, or gzip budget, or when a forbidden (product-/page-specific) chunk
 * becomes statically reachable from the entry. See
 * docs/architecture/frontend-asset-boundaries.md for the rationale.
 */
import { readFileSync, statSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync } from 'node:zlib';

import { evaluateBudgetConfig } from './lib/viteAssetBudget.mjs';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const configPath = join(repoRoot, 'scripts', 'asset-budget.json');
const buildDir = join(repoRoot, 'public', 'build');
const manifestCandidates = [
    join(buildDir, 'manifest.json'),
    join(buildDir, '.vite', 'manifest.json'),
];

function readJson(path) {
    try {
        return JSON.parse(readFileSync(path, 'utf8'));
    } catch (error) {
        throw new Error(`Failed to read JSON from ${path}: ${error.message}`);
    }
}

function findManifestPath() {
    for (const candidate of manifestCandidates) {
        try {
            statSync(candidate);

            return candidate;
        } catch {
            // Try the next candidate.
        }
    }

    throw new Error(
        `No Vite manifest found (looked for ${manifestCandidates.join(', ')}). ` +
            'Run "npm run build" first.',
    );
}

function measureChunk(file) {
    const contents = readFileSync(join(buildDir, file));

    return {
        bytes: contents.byteLength,
        gzipBytes: gzipSync(contents, { level: 9 }).byteLength,
    };
}

function formatKb(bytes) {
    return `${(bytes / 1024).toFixed(1)} KB`;
}

try {
    const config = readJson(configPath);
    const manifest = readJson(findManifestPath());
    const { results, passed } = evaluateBudgetConfig({
        manifest,
        config,
        measureChunk,
    });

    for (const result of results) {
        const budget = config.entries[result.entryName];

        console.log(`\nEntry: ${result.entryName}`);
        console.log(
            `  static chunks : ${result.chunkCount}` +
                (budget.maxStaticChunks !== undefined ? ` / ${budget.maxStaticChunks}` : ''),
        );
        console.log(
            `  static bytes  : ${formatKb(result.totalBytes)}` +
                (budget.maxStaticBytes !== undefined
                    ? ` / ${formatKb(budget.maxStaticBytes)}`
                    : ''),
        );
        console.log(
            `  gzip bytes    : ${formatKb(result.totalGzipBytes)}` +
                (budget.maxGzipBytes !== undefined
                    ? ` / ${formatKb(budget.maxGzipBytes)}`
                    : ''),
        );
        console.log('  largest static chunks:');

        for (const chunk of result.chunks.slice(0, 5)) {
            console.log(
                `    ${formatKb(chunk.bytes).padStart(9)}  (gzip ${formatKb(chunk.gzipBytes)})  ${chunk.key}`,
            );
        }

        for (const violation of result.violations) {
            console.error(`  BUDGET VIOLATION: ${violation}`);
        }
    }

    if (!passed) {
        console.error(
            '\nAsset budget check failed. Keep product- and page-specific code behind ' +
                'dynamic imports (see docs/architecture/frontend-asset-boundaries.md). ' +
                'Raise a budget only with a measured justification.',
        );
        process.exit(1);
    }

    console.log('\nAsset budget check passed.');
} catch (error) {
    console.error(`Asset budget check errored: ${error.message}`);
    process.exit(1);
}
