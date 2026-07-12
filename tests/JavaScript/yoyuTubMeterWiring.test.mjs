import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const indexVue = readFileSync(join(root, 'resources/js/pages/Yoyu/Index.vue'), 'utf8');
const tubVue = readFileSync(join(root, 'resources/js/components/yoyu/YoyuTub.vue'), 'utf8');
const pollTs = readFileSync(
    join(root, 'resources/js/composables/useYoyuBriefingPoll.ts'),
    'utf8',
);

describe('YoyuTub / server meter wiring', () => {
    it('passes custom prep/buffer into YoyuTub and tubStatus', () => {
        assert.match(indexVue, /:prep-minutes="prepMin"/);
        assert.match(indexVue, /:buffer-minutes="bufferMin"/);
        assert.match(
            indexVue,
            /yoyuCalc\(\s*nowMs\.value,\s*props\.calendar,\s*doneEventIds\.value,\s*props\.tasks,\s*prepMin\.value,\s*bufferMin\.value,\s*\)/,
        );
        assert.match(tubVue, /props\.prepMinutes/);
        assert.match(tubVue, /props\.bufferMinutes/);
        assert.match(
            tubVue,
            /yoyuCalc\(\s*props\.nowMs,\s*props\.calendar,\s*props\.doneEventIds,\s*props\.tasks,\s*props\.prepMinutes,\s*props\.bufferMinutes,\s*\)/,
        );
    });

    it('distinguishes client tub and server day meter in UI copy', () => {
        assert.match(tubVue, /いまからの湯加減/);
        assert.match(indexVue, /今日全体の余裕メーター/);
        assert.match(indexVue, /いまからの湯加減/);
        assert.match(indexVue, /07:00–23:00/);
    });

    it('renders server meter from structured/live analysis props', () => {
        assert.match(indexVue, /fromStructured\?\.margin_score/);
        assert.match(indexVue, /fromLive\?\.margin_score/);
        assert.match(indexVue, /meter\.score/);
        assert.match(indexVue, /meter\.busy/);
    });
});

describe('useYoyuBriefingPoll', () => {
    it('partial-reloads structuredBriefing and only while pending/generating', () => {
        assert.match(
            pollTs,
            /only:\s*\[['"]briefing['"],\s*['"]briefingStatus['"],\s*['"]structuredBriefing['"]\]/,
        );
        assert.match(pollTs, /PENDING_STATUSES\s*=\s*new Set\(\['pending',\s*'generating'\]\)/);
        assert.match(pollTs, /enabled:\s*hasPending/);
    });
});
