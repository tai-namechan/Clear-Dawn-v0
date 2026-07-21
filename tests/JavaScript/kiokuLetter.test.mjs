import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    KIOKU_LETTER_CHARACTERS,
    KIOKU_LETTER_EMPTY_MESSAGE,
    KIOKU_LETTER_EMPTY_MESSAGE_DAILY,
    KIOKU_LETTER_FAILED_MESSAGE,
    KIOKU_LETTER_HALTED_MESSAGE,
    KIOKU_LETTER_PAUSED_MESSAGE,
    KIOKU_LETTER_SENSITIVE_VERDICT,
    KIOKU_LETTER_VERDICTS,
    groupKiokuLetterHistory,
    kiokuLetterCharacterCssVars,
    kiokuLetterCharacterMeta,
    kiokuLetterDailyLabel,
    kiokuLetterHomeMode,
    kiokuLetterPreviewLabel,
    kiokuLetterPreviewMode,
    kiokuLetterTitleLabel,
    kiokuLetterWeekLabel,
} from '../../resources/js/lib/kiokuLetter.mjs';

describe('KIOKU_LETTER_CHARACTERS', () => {
    it('defines both variants completely', () => {
        assert.deepEqual(Object.keys(KIOKU_LETTER_CHARACTERS).sort(), [
            'nagi',
            'shiori',
        ]);

        for (const variant of ['shiori', 'nagi']) {
            const meta = KIOKU_LETTER_CHARACTERS[variant];
            assert.ok(meta.name.length > 0, `${variant} name`);
            assert.ok(meta.role.length > 0, `${variant} role`);
            assert.ok(
                meta.signature.includes(meta.name),
                `${variant} signature contains the name`,
            );
            assert.ok(
                meta.signature.includes(meta.role),
                `${variant} signature contains the role`,
            );
            assert.ok(meta.width > 0 && meta.height > 0, `${variant} size`);

            for (const key of [
                'accent',
                'accentSoft',
                'accentDeep',
                'highlight',
            ]) {
                assert.match(
                    meta.colors[key],
                    /^#[0-9A-Fa-f]{6}$/,
                    `${variant} colors.${key}`,
                );
            }
        }
    });

    it('fixes the specified roles and signatures', () => {
        assert.equal(
            KIOKU_LETTER_CHARACTERS.shiori.signature,
            '記憶の案内役 シオリ',
        );
        assert.equal(
            KIOKU_LETTER_CHARACTERS.nagi.signature,
            '記憶の配達役 ナギ',
        );
        assert.equal(KIOKU_LETTER_CHARACTERS.shiori.theme, 'violet');
        assert.equal(KIOKU_LETTER_CHARACTERS.nagi.theme, 'navy');
    });

    it('falls back to shiori for unknown variants', () => {
        assert.equal(kiokuLetterCharacterMeta('unknown').name, 'シオリ');
        assert.equal(kiokuLetterCharacterMeta(null).name, 'シオリ');
        assert.equal(kiokuLetterCharacterMeta('nagi').name, 'ナギ');
    });

    it('exposes the same CSS variable set for both variants', () => {
        const shioriVars = kiokuLetterCharacterCssVars('shiori');
        const nagiVars = kiokuLetterCharacterCssVars('nagi');

        assert.deepEqual(
            Object.keys(shioriVars).sort(),
            Object.keys(nagiVars).sort(),
        );
        assert.notDeepEqual(shioriVars, nagiVars);
    });
});

describe('KIOKU_LETTER_VERDICTS', () => {
    it('keeps the three normal verdicts apart from sensitive_leak', () => {
        assert.deepEqual(
            KIOKU_LETTER_VERDICTS.map((option) => option.value),
            ['hit', 'soft_hit', 'miss'],
        );
        assert.equal(KIOKU_LETTER_SENSITIVE_VERDICT.value, 'sensitive_leak');
        assert.ok(
            !KIOKU_LETTER_VERDICTS.some(
                (option) => option.value === 'sensitive_leak',
            ),
        );
    });
});

describe('kiokuLetterPreviewMode / kiokuLetterPreviewLabel', () => {
    const base = {
        item_count: 3,
        judged_count: 0,
        hit_count: 0,
    };

    it('shows the fixed message for empty letters', () => {
        const letter = { ...base, status: 'empty', opened: false };
        assert.equal(kiokuLetterPreviewMode(letter), 'empty');
        assert.equal(
            kiokuLetterPreviewLabel(letter),
            KIOKU_LETTER_EMPTY_MESSAGE,
        );
    });

    it('treats an unopened published letter as unread', () => {
        const letter = { ...base, status: 'published', opened: false };
        assert.equal(kiokuLetterPreviewMode(letter), 'unread');
    });

    it('reports judged progress while evaluating', () => {
        const letter = {
            ...base,
            status: 'evaluating',
            opened: true,
            judged_count: 2,
        };
        assert.equal(kiokuLetterPreviewMode(letter), 'in_progress');
        assert.equal(kiokuLetterPreviewLabel(letter), '3件中2件を判定済み');
    });

    it('reports HIT count once evaluated', () => {
        const letter = {
            ...base,
            status: 'evaluated',
            opened: true,
            judged_count: 3,
            hit_count: 2,
        };
        assert.equal(kiokuLetterPreviewMode(letter), 'done');
        assert.equal(
            kiokuLetterPreviewLabel(letter),
            '評価済み · HIT 2 / 3件',
        );
    });

    it('surfaces halted separately from empty/in_progress', () => {
        const letter = {
            ...base,
            status: 'halted',
            opened: true,
            judged_count: 1,
        };
        assert.equal(kiokuLetterPreviewMode(letter), 'halted');
    });
});

describe('kiokuLetterHomeMode', () => {
    it('does not treat halt/pause/failed as quiet empty', () => {
        assert.equal(kiokuLetterHomeMode(null, { state: 'halted' }), 'schedule_halted');
        assert.equal(kiokuLetterHomeMode(null, { state: 'paused' }), 'schedule_paused');
        assert.equal(
            kiokuLetterHomeMode({ status: 'failed', opened: false }),
            'failed',
        );
        assert.equal(
            kiokuLetterPreviewLabel({
                status: 'failed',
                opened: false,
                item_count: 0,
                judged_count: 0,
                hit_count: 0,
            }),
            KIOKU_LETTER_FAILED_MESSAGE,
        );
        assert.equal(
            kiokuLetterPreviewLabel({
                status: 'halted',
                opened: true,
                item_count: 1,
                judged_count: 0,
                hit_count: 0,
            }),
            KIOKU_LETTER_HALTED_MESSAGE,
        );
        assert.match(KIOKU_LETTER_PAUSED_MESSAGE, /一時停止/);
    });
});

describe('groupKiokuLetterHistory', () => {
    it('collapses consecutive empty letters without hiding halted', () => {
        const groups = groupKiokuLetterHistory([
            { id: '1', status: 'empty' },
            { id: '2', status: 'empty' },
            { id: '3', status: 'halted' },
            { id: '4', status: 'empty' },
            { id: '5', status: 'published' },
        ]);

        assert.equal(groups[0].type, 'empty_run');
        assert.equal(groups[0].count, 2);
        assert.equal(groups[1].type, 'letter');
        assert.equal(groups[1].letter.status, 'halted');
        assert.equal(groups[2].type, 'empty_run');
        assert.equal(groups[2].count, 1);
        assert.equal(groups[3].letter.status, 'published');
    });
});

describe('kiokuLetterWeekLabel', () => {
    it('formats an ISO date as a Japanese week label', () => {
        assert.equal(kiokuLetterWeekLabel('2026-07-13'), '7/13の週');
    });

    it('returns the input when unparsable', () => {
        assert.equal(kiokuLetterWeekLabel('unknown'), 'unknown');
    });
});

describe('kiokuLetterDailyLabel / kiokuLetterTitleLabel', () => {
    it('formats daily delivery dates', () => {
        assert.equal(
            kiokuLetterDailyLabel('2026-07-15'),
            '2026/7/15のキオク便り',
        );
    });

    it('switches title by cadence', () => {
        assert.equal(
            kiokuLetterTitleLabel({
                cadence: 'daily',
                delivery_date: '2026-07-15',
                week_start: '2026-07-13',
            }),
            '2026/7/15のキオク便り',
        );
        assert.equal(
            kiokuLetterTitleLabel({
                cadence: 'weekly',
                week_start: '2026-07-13',
            }),
            '7/13の週のキオク便り',
        );
    });

    it('uses the daily empty copy for daily cadence', () => {
        assert.equal(
            kiokuLetterPreviewLabel({
                status: 'empty',
                opened: false,
                item_count: 0,
                judged_count: 0,
                hit_count: 0,
                cadence: 'daily',
            }),
            KIOKU_LETTER_EMPTY_MESSAGE_DAILY,
        );
    });
});
