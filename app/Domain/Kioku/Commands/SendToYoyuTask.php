<?php

namespace App\Domain\Kioku\Commands;

use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\MemoryActionExportService;

/**
 * Explicit user action: send a memory to Yoyu as a task.
 */
final class SendToYoyuTask
{
    public function __construct(private MemoryActionExportService $actions) {}

    /**
     * @return array{created: bool, target_id: string}
     */
    public function handle(Memory $memory): array
    {
        $result = $this->actions->sendToYoyu($memory);

        return [
            'created' => $result['created'],
            'target_id' => $result['target_id'],
        ];
    }
}
