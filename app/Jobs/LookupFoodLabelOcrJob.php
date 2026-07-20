<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 成分表画像を AiGateway(vision) で読み取る（設計 §13.4 手順4〜7）。
 * 画面リクエスト中に AI 通信しない原則のため、必ず Queue 経由で実行する。
 *
 * NOTE: handle の実装は Phase 3。Phase 2 時点では dispatch 契約のみ確定。
 */
class LookupFoodLabelOcrJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30];

    public int $uniqueFor = 600;

    public function __construct(public string $lookupRequestId) {}

    public function uniqueId(): string
    {
        return $this->lookupRequestId;
    }

    public function handle(): void
    {
        // Phase 3 で実装
    }
}
