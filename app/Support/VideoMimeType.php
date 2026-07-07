<?php

namespace App\Support;

class VideoMimeType
{
    /** @var list<string> */
    public const ALLOWED = [
        'video/mp4',
        'video/quicktime',
        'video/webm',
    ];

    /**
     * @return array<string, string>
     */
    private const EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
    ];

    public static function extensionFor(string $mimeType): string
    {
        return self::EXTENSIONS[$mimeType]
            ?? throw new \InvalidArgumentException("Unsupported mime type: {$mimeType}");
    }

    public static function isAllowed(string $mimeType): bool
    {
        return in_array($mimeType, self::ALLOWED, true);
    }
}
