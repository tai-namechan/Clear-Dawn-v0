import { createReadStream, statSync } from 'node:fs';
import path from 'node:path';

/**
 * Static asset types the dev server may hand out from `public/`.
 * Anything else (index.php, .htaccess, robots.txt, ...) stays with Laravel.
 *
 * @type {Record<string, string>}
 */
const PUBLIC_ASSET_CONTENT_TYPES = {
    '.avif': 'image/avif',
    '.gif': 'image/gif',
    '.ico': 'image/x-icon',
    '.jpeg': 'image/jpeg',
    '.jpg': 'image/jpeg',
    '.png': 'image/png',
    '.svg': 'image/svg+xml',
    '.webp': 'image/webp',
};

/**
 * @param {string} filePath
 * @returns {string | null}
 */
export function publicAssetContentType(filePath) {
    return PUBLIC_ASSET_CONTENT_TYPES[path.extname(filePath).toLowerCase()] ?? null;
}

/**
 * Map a dev-server request URL onto a file inside `publicRoot`.
 * Returns null when the request is not an existing static asset there,
 * including any attempt to escape the directory.
 *
 * @param {string} publicRoot
 * @param {string | undefined} requestUrl
 * @returns {string | null}
 */
export function resolvePublicAssetPath(publicRoot, requestUrl) {
    if (!requestUrl) {
        return null;
    }

    let pathname;

    try {
        pathname = decodeURIComponent(new URL(requestUrl, 'http://localhost').pathname);
    } catch {
        return null;
    }

    const root = path.resolve(publicRoot);
    const filePath = path.resolve(root, `.${pathname}`);

    if (!filePath.startsWith(root + path.sep)) {
        return null;
    }

    if (publicAssetContentType(filePath) === null) {
        return null;
    }

    try {
        return statSync(filePath).isFile() ? filePath : null;
    } catch {
        return null;
    }
}

/**
 * laravel-vite-plugin disables Vite's publicDir, but in dev Vite rewrites
 * root-absolute CSS urls such as url('/images/x.png') onto the dev server
 * origin, where they 404. Serve those images from `public/` during
 * `vite` (serve) only, so dev matches the production build.
 *
 * @param {{ publicDirectory?: string }} [options]
 * @returns {import('vite').Plugin}
 */
export function servePublicAssetsInDev({ publicDirectory = 'public' } = {}) {
    return {
        name: 'clear-dawn:serve-public-assets-in-dev',
        apply: 'serve',
        configureServer(server) {
            const publicRoot = path.resolve(server.config.root, publicDirectory);

            server.middlewares.use((request, response, next) => {
                if (request.method !== 'GET' && request.method !== 'HEAD') {
                    next();

                    return;
                }

                const filePath = resolvePublicAssetPath(publicRoot, request.url);

                if (filePath === null) {
                    next();

                    return;
                }

                response.setHeader('Content-Type', publicAssetContentType(filePath));
                response.setHeader('Cache-Control', 'no-cache');

                if (request.method === 'HEAD') {
                    response.end();

                    return;
                }

                createReadStream(filePath).on('error', next).pipe(response);
            });
        },
    };
}
