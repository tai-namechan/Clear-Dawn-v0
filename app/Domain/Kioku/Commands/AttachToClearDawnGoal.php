<?php

namespace App\Domain\Kioku\Commands;

use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\MemoryActionExportService;

/**
 * Explicit user action: attach memory provenance to Clear Dawn context.
 */
final class AttachToClearDawnGoal
{
    public function __construct(private MemoryActionExportService $actions) {}

    /**
     * @return array{created: bool, target_id: string, preview: string}
     */
    public function handle(Memory $memory, ?string $goalId = null): array
    {
        $result = $this->actions->sendToClearDawn($memory);

        return [
            'created' => $result['created'],
            'target_id' => $result['target_id'],
            'preview' => $result['preview'],
        ];
    }
}
