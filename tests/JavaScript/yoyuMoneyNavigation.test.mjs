/**
 * @typedef {{ key: string, label: string, href: string, matchPrefixes: string[] }} MoneyPrimaryNavItem
 */

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');

function loadNavigationSource() {
    return readFileSync(
        join(root, 'resources/js/lib/yoyuMoney/navigation.ts'),
        'utf8',
    );
}

function loadLabelsSource() {
    return readFileSync(
        join(root, 'resources/js/lib/yoyuMoney/labels.ts'),
        'utf8',
    );
}

describe('yoyu money navigation IA', () => {
    it('defines five primary nav items', () => {
        const source = loadNavigationSource();

        assert.match(source, /key: 'home'/);
        assert.match(source, /key: 'month'/);
        assert.match(source, /key: 'assets'/);
        assert.match(source, /key: 'ledger'/);
        assert.match(source, /key: 'plan'/);
        assert.match(source, /label: 'ホーム'/);
        assert.match(source, /label: '今月'/);
        assert.match(source, /label: '資産・返済'/);
        assert.match(source, /label: '明細'/);
        assert.match(source, /label: '計画'/);
    });

    it('maps legacy urls to section groups', () => {
        const source = loadNavigationSource();

        assert.match(source, /\/yoyu\/money\/accounts/);
        assert.match(source, /\/yoyu\/money\/cards/);
        assert.match(source, /\/yoyu\/money\/loans/);
        assert.match(source, /\/yoyu\/money\/transactions/);
        assert.match(source, /\/yoyu\/money\/imports/);
        assert.match(source, /\/yoyu\/money\/analysis/);
        assert.match(source, /\/yoyu\/money\/simulations/);
        assert.match(source, /\/yoyu\/money\/decisions/);
        assert.match(source, /見直したこと/);
    });

    it('keeps card payment wording as reference not double spend', () => {
        const labels = loadLabelsSource();

        assert.match(
            labels,
            /card_payment: 'カード請求に含まれるため参考表示'/,
        );
        assert.doesNotMatch(labels, /浪費|借金地獄|今すぐリボ/);
    });
});
