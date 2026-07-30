<?php

namespace App\Domain\Kioku\Capture;

use App\Domain\Kioku\Models\Memory;

interface MemoryProcessingPipeline
{
    public function dispatchAfterCapture(Memory $memory): void;

    public function resumeFrom(Memory $memory, ProcessingStage $stage): void;
}
