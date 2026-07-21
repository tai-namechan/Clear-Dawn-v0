import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    relatedMemoryReason,
    sharedMemoryTags,
} from '../../resources/js/lib/kiokuRelated.mjs';

describe('sharedMemoryTags', () => {
    it('returns intersection preserving left order', () => {
        assert.deepEqual(
            sharedMemoryTags(['AI', '自動化', '仕事'], ['仕事', 'AI', '趣味']),
            ['AI', '仕事'],
        );
    });

    it('ignores blanks and duplicates', () => {
        assert.deepEqual(
            sharedMemoryTags(['AI', 'AI', '', null], ['AI', '']),
            ['AI'],
        );
    });
});

describe('relatedMemoryReason', () => {
    it('prefers shared tags when present', () => {
        assert.equal(
            relatedMemoryReason(
                { tags: ['AI', '自動化'] },
                { tags: ['自動化', 'メモ'] },
            ),
            '共通タグ: #自動化',
        );
    });

    it('falls back to a generic non-AI claim', () => {
        assert.equal(
            relatedMemoryReason({ tags: ['A'] }, { tags: ['B'] }),
            '内容や関連付けから見つかりました',
        );
    });
});
