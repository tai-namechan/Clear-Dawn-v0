<?php

namespace App\Domain\Kioku\Commands;

use App\Domain\Kioku\Models\Memory;

/**
 * Stub: send a memory to Yoyu as a task. Implemented in Yoyu milestone.
 */
final class SendToYoyuTask
{
    public function handle(Memory $memory): void
    {
        // TODO: create YoyuTask from memory and link via memory_links
    }
}
