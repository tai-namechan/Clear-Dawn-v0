import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

/**
 * Static guards for docs/architecture/frontend-asset-boundaries.md.
 *
 * The page-count invariant (adding pages must not grow unrelated screens'
 * initial load) relies on the Inertia page resolver staying non-eager and on
 * app.ts not statically importing page or product code. These guards fail the
 * moment either regresses, without needing a production build.
 */

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const jsRoot = join(repoRoot, 'resources', 'js');

function collectSourceFiles(directory) {
    const files = [];

    for (const entry of readdirSync(directory, { withFileTypes: true })) {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            files.push(...collectSourceFiles(path));
        } else if (/\.(ts|vue|js|mjs)$/.test(entry.name)) {
            files.push(path);
        }
    }

    return files;
}

describe('frontend asset boundaries', () => {
    it('never resolves pages with an eager import.meta.glob', () => {
        const offenders = collectSourceFiles(jsRoot).filter((path) => {
            const source = readFileSync(path, 'utf8');

            return /import\.meta\.glob\([^)]*eager:\s*true/s.test(source);
        });

        assert.deepEqual(
            offenders,
            [],
            'eager import.meta.glob pulls every matched module into the entry chunk, ' +
                'so each new page would grow every screen\'s initial load (FR-ASSET-001).',
        );
    });

    it('keeps the Inertia page resolver lazy in vite.config.ts', () => {
        const viteConfig = readFileSync(join(repoRoot, 'vite.config.ts'), 'utf8');

        assert.ok(
            !/lazy:\s*false/.test(viteConfig),
            'inertia({ lazy: false }) switches the injected page resolver to eager ' +
                'import.meta.glob, breaking FR-ASSET-001.',
        );
    });

    it('keeps app.ts free of static page and product imports', () => {
        const appEntry = readFileSync(join(jsRoot, 'app.ts'), 'utf8');
        const staticImports = [
            ...appEntry.matchAll(/^import\s[^;]*?from\s+['"]([^'"]+)['"]/gms),
        ].map(([, specifier]) => specifier);
        const offenders = staticImports.filter((specifier) =>
            /@\/pages|\/pages\/|kioku|yoyu|video/i.test(specifier),
        );

        assert.deepEqual(
            offenders,
            [],
            'app.ts is the shared entry; page and product code must load through ' +
                'the lazy page resolver or a dynamic import (FR-ASSET-002/003).',
        );
    });
});
