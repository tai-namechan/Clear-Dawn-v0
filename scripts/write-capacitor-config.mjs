import { writeFileSync } from 'node:fs';
import {
    appConfigScript,
    resolveCapacitorServerUrl,
} from './lib/capacitorServer.mjs';

/** Writes capacitor/www/app-config.js from CAP_SERVER_URL before `cap sync`. */
const server = resolveCapacitorServerUrl(process.env.CAP_SERVER_URL);

writeFileSync(
    new URL('../capacitor/www/app-config.js', import.meta.url),
    appConfigScript(server?.url ?? null),
);

console.log(
    server
        ? `CAP_SERVER_URL: ${server.url}`
        : 'CAP_SERVER_URL is not set (the app will show the bundled page).',
);
