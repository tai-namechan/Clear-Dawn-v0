import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { backgroundScene } from '../../resources/js/lib/backgroundScene.mjs';

describe('backgroundScene', () => {
    it('maps each page group to its wa-modern background', () => {
        assert.equal(backgroundScene('Dashboard'), 'today');
        assert.equal(backgroundScene('Goals/Index'), 'today');
        assert.equal(backgroundScene('Meals/Index'), 'food');
        assert.equal(backgroundScene('Meals/Foods'), 'food');
        assert.equal(backgroundScene('Records/Index'), 'condition');
        assert.equal(backgroundScene('Records/Condition'), 'condition');
        assert.equal(backgroundScene('Routines/Index'), 'practice');
        assert.equal(backgroundScene('Sessions/Show'), 'practice');
        assert.equal(backgroundScene('Programs/Roadmap'), 'practice');
        assert.equal(backgroundScene('Videos/Index'), 'practice');
        assert.equal(backgroundScene('settings/Profile'), 'settings');
    });

    it('falls back to the calm settings background', () => {
        assert.equal(backgroundScene('Unknown/Page'), 'settings');
        assert.equal(backgroundScene(undefined), 'settings');
    });
});
