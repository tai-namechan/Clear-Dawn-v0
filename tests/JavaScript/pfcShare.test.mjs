import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { pfcEnergyShare } from '../../resources/js/lib/pfcShare.mjs';

describe('pfcEnergyShare', () => {
    it('weights grams by 4 / 9 / 4 kcal', () => {
        // 140.2g P = 560.8, 49.3g F = 443.7, 234.5g C = 938 → total 1942.5
        assert.deepEqual(
            pfcEnergyShare({ protein_g: 140.2, fat_g: 49.3, carb_g: 234.5 }),
            { p: 29, f: 23, c: 48 },
        );
    });

    it('accepts numeric strings from the API', () => {
        assert.deepEqual(
            pfcEnergyShare({ protein_g: '25', fat_g: '0', carb_g: '25' }),
            { p: 50, f: 0, c: 50 },
        );
    });

    it('returns zeros when nothing was eaten or values are missing', () => {
        assert.deepEqual(pfcEnergyShare({ protein_g: 0, fat_g: 0, carb_g: 0 }), { p: 0, f: 0, c: 0 });
        assert.deepEqual(pfcEnergyShare({ protein_g: null, fat_g: undefined, carb_g: 'x' }), { p: 0, f: 0, c: 0 });
    });
});
