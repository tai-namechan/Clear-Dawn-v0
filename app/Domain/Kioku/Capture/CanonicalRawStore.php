<?php

namespace App\Domain\Kioku\Capture;

use App\Domain\Kioku\Capture\Dto\CapturedRaw;
use App\Domain\Kioku\Models\Memory;

/**
 * Persists canonical raw without waiting on AI. Idempotent on client_capture_id.
 */
interface CanonicalRawStore
{
    /**
     * @return array{memory: Memory, created: bool}
     */
    public function persist(CapturedRaw $raw): array;
}
