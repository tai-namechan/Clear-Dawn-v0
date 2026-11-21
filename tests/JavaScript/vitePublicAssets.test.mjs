import assert from 'node:assert/strict';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { after, before, describe, it } from 'node:test';
import {
    publicAssetContentType,
    resolvePublicAssetPath,
    servePublicAssetsInDev,
} from '../../scripts/lib/vitePublicAssets.mjs';

let projectRoot;
let publicRoot;

before(() => {
    projectRoot = mkdtempSync(path.join(tmpdir(), 'vite-public-assets-'));
    publicRoot = path.join(projectRoot, 'public');
    mkdirSync(path.join(publicRoot, 'images', 'backgrounds'), { recursive: true });
    writeFileSync(path.join(publicRoot, 'images', 'backgrounds', 'top background.png'), 'png-bytes');
    writeFileSync(path.join(publicRoot, 'images', 'stars.svg'), '<svg/>');
    writeFileSync(path.join(publicRoot, 'index.php'), '<?php');
    writeFileSync(path.join(projectRoot, 'secret.png'), 'outside');
});

after(() => {
    rmSync(projectRoot, { recursive: true, force: true });
});

describe('publicAssetContentType', () => {
    it('maps image extensions case-insensitively', () => {
        assert.equal(publicAssetContentType('a/b.PNG'), 'image/png');
        assert.equal(publicAssetContentType('a/b.svg'), 'image/svg+xml');
    });

    it('returns null for non-image files', () => {
        assert.equal(publicAssetContentType('index.php'), null);
        assert.equal(publicAssetContentType('.htaccess'), null);
    });
});

describe('resolvePublicAssetPath', () => {
    it('resolves an existing image, ignoring the query string and decoding the path', () => {
        assert.equal(
            resolvePublicAssetPath(publicRoot, '/images/backgrounds/top%20background.png?v=1'),
            path.join(publicRoot, 'images', 'backgrounds', 'top background.png'),
        );
    });

    it('returns null for missing files, directories and empty urls', () => {
        assert.equal(resolvePublicAssetPath(publicRoot, '/images/missing.png'), null);
        assert.equal(resolvePublicAssetPath(publicRoot, '/images/backgrounds'), null);
        assert.equal(resolvePublicAssetPath(publicRoot, undefined), null);
    });

    it('never hands out non-image files such as index.php', () => {
        assert.equal(resolvePublicAssetPath(publicRoot, '/index.php'), null);
        assert.equal(resolvePublicAssetPath(publicRoot, '/resources/css/app.css'), null);
    });

    it('refuses to escape the public directory', () => {
        assert.equal(resolvePublicAssetPath(publicRoot, '/../secret.png'), null);
        assert.equal(resolvePublicAssetPath(publicRoot, '/%2e%2e/secret.png'), null);
        assert.equal(resolvePublicAssetPath(publicRoot, '/images/%2e%2e/%2e%2e/secret.png'), null);
    });

    it('returns null for malformed percent-encoding', () => {
        assert.equal(resolvePublicAssetPath(publicRoot, '/images/%E0%A4%A.png'), null);
    });
});

describe('servePublicAssetsInDev', () => {
    function createMiddleware() {
        const plugin = servePublicAssetsInDev();
        let middleware;

        plugin.configureServer({
            config: { root: projectRoot },
            middlewares: {
                use(handler) {
                    middleware = handler;
                },
            },
        });

        return middleware;
    }

    function invoke(middleware, method, url) {
        return new Promise((resolve, reject) => {
            const headers = {};
            const chunks = [];
            const response = {
                setHeader(name, value) {
                    headers[name] = value;
                },
                write(chunk) {
                    chunks.push(Buffer.from(chunk));

                    return true;
                },
                end(chunk) {
                    if (chunk) {
                        chunks.push(Buffer.from(chunk));
                    }

                    resolve({ passedThrough: false, headers, body: Buffer.concat(chunks).toString() });
                },
                on() {
                    return response;
                },
                once() {
                    return response;
                },
                emit() {
                    return true;
                },
                removeListener() {
                    return response;
                },
            };

            try {
                middleware({ method, url }, response, (error) => {
                    if (error) {
                        reject(error);

                        return;
                    }

                    resolve({ passedThrough: true, headers, body: '' });
                });
            } catch (error) {
                reject(error);
            }
        });
    }

    it('only applies to the dev server', () => {
        assert.equal(servePublicAssetsInDev().apply, 'serve');
    });

    it('serves a public image with its content type', async () => {
        const result = await invoke(createMiddleware(), 'GET', '/images/stars.svg');

        assert.equal(result.passedThrough, false);
        assert.equal(result.headers['Content-Type'], 'image/svg+xml');
        assert.equal(result.body, '<svg/>');
    });

    it('answers HEAD without a body', async () => {
        const result = await invoke(createMiddleware(), 'HEAD', '/images/stars.svg');

        assert.equal(result.passedThrough, false);
        assert.equal(result.headers['Content-Type'], 'image/svg+xml');
        assert.equal(result.body, '');
    });

    it('passes everything else through to Vite', async () => {
        const middleware = createMiddleware();

        assert.equal((await invoke(middleware, 'GET', '/resources/js/app.ts')).passedThrough, true);
        assert.equal((await invoke(middleware, 'GET', '/index.php')).passedThrough, true);
        assert.equal((await invoke(middleware, 'POST', '/images/stars.svg')).passedThrough, true);
    });
});
