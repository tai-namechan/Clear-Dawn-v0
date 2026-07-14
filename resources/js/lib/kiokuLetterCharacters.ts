import nagiAsset from '@/assets/kioku/concierge/nagi.webp';
import shioriAsset from '@/assets/kioku/concierge/shiori.webp';
import { KIOKU_LETTER_CHARACTERS } from '@/lib/kiokuLetter.mjs';
import type { KiokuLetterCharacterMeta } from '@/lib/kiokuLetter.mjs';

/**
 * Character meta joined with the bundled WebP assets. Imported only from
 * Kioku letter components so the images stay inside the letter's dynamic
 * chunk (never app.ts / OS shell).
 */
export type KiokuLetterCharacter = KiokuLetterCharacterMeta & {
    asset: string;
};

export const kiokuLetterCharacters: Record<
    'shiori' | 'nagi',
    KiokuLetterCharacter
> = {
    shiori: { ...KIOKU_LETTER_CHARACTERS.shiori, asset: shioriAsset },
    nagi: { ...KIOKU_LETTER_CHARACTERS.nagi, asset: nagiAsset },
};

export function kiokuLetterCharacter(
    variant: string | null | undefined,
): KiokuLetterCharacter {
    return variant === 'nagi'
        ? kiokuLetterCharacters.nagi
        : kiokuLetterCharacters.shiori;
}
