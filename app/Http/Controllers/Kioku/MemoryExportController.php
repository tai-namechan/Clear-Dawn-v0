<?php

namespace App\Http\Controllers\Kioku;

use App\Domain\Kioku\Export\MemoryMarkdownExporter;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\MemoryActionExportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class MemoryExportController extends Controller
{
    public function obsidianZip(Request $request, MemoryMarkdownExporter $exporter): StreamedResponse
    {
        abort_unless(config('kioku.obsidian_export.enabled', false), 404);

        $userId = (int) $request->user()->id;
        $includeTranscript = $request->boolean('include_transcript');
        $includeSensitive = $request->boolean('include_sensitive');
        $ids = array_values(array_filter(
            (array) $request->input('memory_ids', []),
            fn ($id) => is_string($id) && $id !== '',
        ));

        $query = Memory::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('status', 'ready')
            ->when(! $includeSensitive, fn ($q) => $q->where('sensitive', false))
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->orderByDesc('captured_at')
            ->limit(500);

        $memories = array_values($query->get()->all());
        $files = $exporter->exportMany($memories, $includeTranscript, $includeSensitive);

        $tmp = tempnam(sys_get_temp_dir(), 'kioku-obsidian-');
        if ($tmp === false) {
            abort(500);
        }

        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        foreach ($files as $name => $body) {
            $zip->addFromString($name, $body);
        }
        $zip->addFromString('_README.md', "# Kioku Obsidian export\n\nSource of truth remains Clear Dawn Kioku.\n");
        $zip->close();

        return ResponseFactory::streamDownload(function () use ($tmp): void {
            echo file_get_contents($tmp);
            @unlink($tmp);
        }, 'kioku-obsidian-'.now()->format('Ymd-His').'.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function sendToYoyu(Request $request, Memory $memory, MemoryActionExportService $actions): RedirectResponse
    {
        abort_unless(config('kioku.action_export.enabled', false), 404);
        $this->authorizeMemory($request, $memory);

        $result = $actions->sendToYoyu($memory);

        return redirect()
            ->route('kioku.memories.show', $memory)
            ->with('success', $result['created']
                ? 'ヨユウにタスクを作成しました'
                : '既にヨユウへ送済みです（重複作成しませんでした）');
    }

    public function sendToClearDawn(Request $request, Memory $memory, MemoryActionExportService $actions): RedirectResponse
    {
        abort_unless(config('kioku.action_export.enabled', false), 404);
        $this->authorizeMemory($request, $memory);

        $result = $actions->sendToClearDawn($memory);

        return redirect()
            ->route('kioku.memories.show', $memory)
            ->with('success', $result['created']
                ? 'Clear Dawn 相談文脈として記録しました'
                : '既に Clear Dawn へ送済みです（重複作成しませんでした）')
            ->with('clear_dawn_preview', $result['preview']);
    }

    private function authorizeMemory(Request $request, Memory $memory): void
    {
        abort_unless((int) $memory->user_id === (int) $request->user()->id, 404);
    }
}
