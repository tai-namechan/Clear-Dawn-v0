<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Models\KiokuActionExport;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Yoyu\Models\YoyuTask;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MemoryActionExportService
{
    public const TARGET_YOYU = 'yoyu_task';

    public const TARGET_CLEAR_DAWN = 'clear_dawn_context';

    /**
     * @return array{created: bool, target_id: string, export: KiokuActionExport}
     */
    public function sendToYoyu(Memory $memory): array
    {
        $this->assertExportable($memory);

        $existing = KiokuActionExport::query()
            ->withoutUserScope()
            ->where('user_id', $memory->user_id)
            ->where('memory_id', $memory->id)
            ->where('target', self::TARGET_YOYU)
            ->first();

        if ($existing !== null) {
            return ['created' => false, 'target_id' => (string) $existing->target_id, 'export' => $existing];
        }

        return DB::transaction(function () use ($memory): array {
            $structured = is_array($memory->structured_data) ? $memory->structured_data : [];
            $title = trim((string) ($memory->title ?: 'キオクからのタスク'));
            $task = YoyuTask::query()->create([
                'user_id' => $memory->user_id,
                'title' => mb_substr($title, 0, 200),
                'status' => 'planned',
                'estimate_minutes' => 30,
                'category' => 'kioku',
                'priority' => 'normal',
            ]);

            $export = KiokuActionExport::query()->create([
                'user_id' => $memory->user_id,
                'memory_id' => $memory->id,
                'target' => self::TARGET_YOYU,
                'target_id' => $task->id,
            ]);

            return ['created' => true, 'target_id' => $task->id, 'export' => $export];
        });
    }

    /**
     * Clear Dawn 相談文脈への明示エクスポート（重複防止付き provenance）。
     *
     * @return array{created: bool, target_id: string, export: KiokuActionExport, preview: string}
     */
    public function sendToClearDawn(Memory $memory): array
    {
        $this->assertExportable($memory);

        $existing = KiokuActionExport::query()
            ->withoutUserScope()
            ->where('user_id', $memory->user_id)
            ->where('memory_id', $memory->id)
            ->where('target', self::TARGET_CLEAR_DAWN)
            ->first();

        $preview = $this->clearDawnPreview($memory);

        if ($existing !== null) {
            return [
                'created' => false,
                'target_id' => (string) $existing->target_id,
                'export' => $existing,
                'preview' => $preview,
            ];
        }

        $export = KiokuActionExport::query()->create([
            'user_id' => $memory->user_id,
            'memory_id' => $memory->id,
            'target' => self::TARGET_CLEAR_DAWN,
            'target_id' => $memory->id,
        ]);

        return [
            'created' => true,
            'target_id' => $memory->id,
            'export' => $export,
            'preview' => $preview,
        ];
    }

    private function assertExportable(Memory $memory): void
    {
        if (! config('kioku.action_export.enabled', false)) {
            throw new RuntimeException('Action export is disabled.');
        }

        if ($memory->sensitive) {
            throw new RuntimeException('Sensitive memories require an explicit non-automated path and are blocked here.');
        }
    }

    private function clearDawnPreview(Memory $memory): string
    {
        $parts = array_filter([
            $memory->title,
            $memory->summary,
            is_array($memory->structured_data) ? ($memory->structured_data['next_action'] ?? null) : null,
        ]);

        return implode("\n\n", array_map(static fn ($p) => (string) $p, $parts));
    }
}
