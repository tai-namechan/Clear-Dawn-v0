<?php

namespace App\Domain\Kioku\Transcription;

use RuntimeException;

/**
 * Permanent transcription failure (configuration, unsupported input,
 * storage read failure, or a provider rejection such as 401/403/422).
 * TranscribeMemoryAudioJob marks the memory failed immediately instead of
 * retrying, so a hopeless request is never re-billed. Messages must never
 * contain API keys, object storage paths, or signed URLs.
 */
final class TranscriptionFailedException extends RuntimeException {}
