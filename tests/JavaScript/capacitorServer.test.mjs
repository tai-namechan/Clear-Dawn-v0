import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    appConfigScript,
    resolveCapacitorServerUrl,
} from '../../scripts/lib/capacitorServer.mjs';

describe('resolveCapacitorServerUrl', () => {
    it('accepts https and keeps only the origin', () => {
        assert.deepEqual(
            resolveCapacitorServerUrl('https://app.example.com/dashboard'),
            {
                url: 'https://app.example.com',
                cleartext: false,
            },
        );
    });

    it('allows http only for local development hosts', () => {
        assert.deepEqual(
            resolveCapacitorServerUrl('http://192.168.1.20:8001'),
            {
                url: 'http://192.168.1.20:8001',
                cleartext: true,
            },
        );
        assert.throws(
            () => resolveCapacitorServerUrl('http://app.example.com'),
            /https/,
        );
    });

    it('returns null when unset and rejects garbage', () => {
        assert.equal(resolveCapacitorServerUrl(undefined), null);
        assert.equal(resolveCapacitorServerUrl('  '), null);
        assert.throws(
            () => resolveCapacitorServerUrl('not a url'),
            /valid URL/,
        );
    });
});

describe('appConfigScript', () => {
    it('writes the URL as a safe JS string', () => {
        assert.equal(
            appConfigScript('https://app.example.com'),
            'window.CD_SERVER_URL = "https://app.example.com";\n',
        );
        assert.equal(appConfigScript(null), 'window.CD_SERVER_URL = null;\n');
    });
});
