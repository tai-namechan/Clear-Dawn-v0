<?php

namespace App\Domain\Kioku\Capture;

use App\Domain\Kioku\Capture\Dto\CaptureCommand;
use App\Domain\Kioku\Capture\Dto\CapturedRaw;

/**
 * Ingress-only boundary. Must not call AI / transcription / enrichment.
 */
interface CaptureAdapter
{
    public function supports(CaptureCommand $command): bool;

    public function toCapturedRaw(CaptureCommand $command): CapturedRaw;
}
