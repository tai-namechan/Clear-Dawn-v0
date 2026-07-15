<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Services\KiokuConciergePilotService;
use App\Models\User;
use Illuminate\Console\Command;

class ReportKiokuConciergePilotCommand extends Command
{
    protected $signature = 'kioku:letters:pilot:report {userId : users.id}';

    protected $description = 'Print daily pilot evaluation metrics (N/A when denominator is 0)';

    public function handle(KiokuConciergePilotService $pilot): int
    {
        $user = User::query()->find((int) $this->argument('userId'));
        if ($user === null) {
            $this->error("User [{$this->argument('userId')}] not found.");

            return self::FAILURE;
        }

        $report = $pilot->report($user);
        $before = $report['memory_capture']['before'];
        $during = $report['memory_capture']['during'];

        $this->table(['metric', 'value'], [
            ['state', $report['state']],
            ['pause_reason', $report['pause_reason'] ?? ''],
            ['generated / pilot_days', $report['generated_label']],
            ['opened within 24h / generated', $report['opened_within_24h_label']],
            ['HIT rate (target ≥25%)', $report['hit_rate_label']],
            ['useful rate (target ≥50%)', $report['useful_rate_label']],
            ['max consecutive unopened', (string) $report['max_consecutive_unopened']],
            ['empty days', (string) $report['empty_days']],
            ['sensitive_leak count (target 0)', (string) $report['sensitive_leak_count']],
            ['memory days/count before', "{$before['days']} / {$before['count']}"],
            ['memory days/count during', "{$during['days']} / {$during['count']}"],
        ]);

        return self::SUCCESS;
    }
}
