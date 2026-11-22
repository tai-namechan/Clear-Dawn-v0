import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
    clearDawnNavItems,
    isNavPathActive,
} from '../../resources/js/lib/clearDawnNavigation.mjs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

function activeKey(path) {
    return clearDawnNavItems.find((item) => isNavPathActive(item.paths, path))?.key ?? null;
}

describe('clearDawnNavItems', () => {
    it('lists the five shared destinations in order', () => {
        assert.deepEqual(
            clearDawnNavItems.map((item) => item.label),
            ['ダッシュボード', 'ルーティン', 'パフォーマンス管理', '食事', '設定'],
        );
        assert.deepEqual(
            clearDawnNavItems.map((item) => item.shortLabel),
            ['ホーム', 'ルーティン', '記録', '食事', '設定'],
        );
    });

    it('does not put goals or videos in the navigation itself', () => {
        const hrefs = clearDawnNavItems.map((item) => item.href);

        assert.ok(!hrefs.includes('/goals'));
        assert.ok(!hrefs.includes('/videos'));
    });

    it('marks routines active on goals and videos pages', () => {
        assert.equal(activeKey('/goals'), 'routines');
        assert.equal(activeKey('/goals/01J000/edit'), 'routines');
        assert.equal(activeKey('/videos?page=2'), 'routines');
        assert.equal(activeKey('/programs/1'), 'routines');
    });

    it('matches each destination by path prefix only', () => {
        assert.equal(activeKey('/dashboard'), 'dashboard');
        assert.equal(activeKey('/history'), 'records');
        assert.equal(activeKey('/meals?date=2026-11-22'), 'meals');
        assert.equal(activeKey('/settings/password'), 'settings');
        assert.equal(activeKey('/mealsx'), null);
    });
});

describe('navigation wiring', () => {
    it('sidebar and mobile nav both read the shared definition', () => {
        assert.match(read('resources/js/components/AppSidebar.vue'), /clearDawnNavigation/);
        assert.match(read('resources/js/components/CdMobileNav.vue'), /clearDawnNavigation/);
    });

    it('routines page links to goals and videos', () => {
        const page = read('resources/js/pages/Routines/Index.vue');

        assert.match(page, /href="\/goals"/);
        assert.match(page, /href="\/videos"/);
    });
});
