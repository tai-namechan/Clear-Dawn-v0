import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { toFormValue } from '../../resources/js/lib/conditionForm.mjs';

describe('toFormValue', () => {
    it('drops trailing zeros from stored decimals', () => {
        assert.equal(toFormValue('2.00'), '2');
        assert.equal(toFormValue('62.60'), '62.6');
        assert.equal(toFormValue('440.00'), '440');
        assert.equal(toFormValue(3), '3');
    });

    it('keeps empty values empty and leaves non-numbers untouched', () => {
        assert.equal(toFormValue(null), '');
        assert.equal(toFormValue(undefined), '');
        assert.equal(toFormValue(''), '');
        assert.equal(toFormValue('abc'), 'abc');
    });
});
