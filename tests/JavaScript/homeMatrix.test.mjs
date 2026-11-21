import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    areaIconKey,
    cellItemCount,
    focusTabLabel,
    formatHomeDate,
} from '../../resources/js/lib/homeMatrix.mjs';

describe('areaIconKey', () => {
    it('infers an icon from the life-area name', () => {
        assert.equal(areaIconKey('野球'), 'ball');
        assert.equal(areaIconKey('バイオリン'), 'music');
        assert.equal(areaIconKey('学び'), 'study');
        assert.equal(areaIconKey('健康'), 'health');
        assert.equal(areaIconKey('仕事'), 'work');
        assert.equal(areaIconKey('プライベート'), 'private');
    });

    it('falls back to a neutral icon for unknown or empty names', () => {
        assert.equal(areaIconKey('ボードゲーム'), 'default');
        assert.equal(areaIconKey(''), 'default');
        assert.equal(areaIconKey(undefined), 'default');
    });
});

describe('formatHomeDate', () => {
    it('formats month, day and weekday in Japanese', () => {
        assert.equal(formatHomeDate(new Date(2026, 10, 22)), '11月22日(日)');
        assert.equal(formatHomeDate(new Date(2026, 7, 16)), '8月16日(日)');
        assert.equal(formatHomeDate(new Date(2026, 0, 5)), '1月5日(月)');
    });
});

describe('focusTabLabel', () => {
    it('shortens the three matrix rows and keeps unknown labels', () => {
        assert.equal(focusTabLabel('monthly', '1ヶ月でやるべきこと'), '今月');
        assert.equal(focusTabLabel('current', '今やるべきこと'), '今');
        assert.equal(focusTabLabel('future', '将来どうなっていたいか'), '将来');
        assert.equal(focusTabLabel('other', 'その他'), 'その他');
    });
});

describe('cellItemCount', () => {
    const rows = [
        { key: 'monthly', cells: [{ items: [1] }, { items: [] }] },
        { key: 'current', cells: [{ items: [1, 2, 3] }, { items: [1] }] },
    ];

    it('counts the items of one row × area cell', () => {
        assert.equal(cellItemCount(rows, 'current', 0), 3);
        assert.equal(cellItemCount(rows, 'current', 1), 1);
        assert.equal(cellItemCount(rows, 'monthly', 1), 0);
    });

    it('returns 0 for a missing row or area', () => {
        assert.equal(cellItemCount(rows, 'future', 0), 0);
        assert.equal(cellItemCount(rows, 'current', 9), 0);
    });
});
