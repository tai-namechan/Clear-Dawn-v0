<?php

namespace App\Domain\Kioku\Capture;

/**
 * Which ingress accepted the capture. Independent of RawKind.
 */
enum CaptureChannel: string
{
    case WebText = 'web_text';
    case WebUrl = 'web_url';
    case BrowserVoice = 'browser_voice';
    case AudioFileImport = 'audio_file_import';
    case IosShortcut = 'ios_shortcut';
    case SystemConnector = 'system_connector';
    case SystemGenerated = 'system_generated';
}
