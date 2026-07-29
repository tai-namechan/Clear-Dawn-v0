<?php

namespace App\Domain\Kioku\Capture;

/**
 * What was persisted as the canonical raw for a Memory.
 */
enum RawKind: string
{
    case Text = 'text';
    case Audio = 'audio';
    case Url = 'url';

    public function isAudio(): bool
    {
        return $this === self::Audio;
    }
}
