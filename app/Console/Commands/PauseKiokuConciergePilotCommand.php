<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\Services\KiokuConciergePilotService;
use App\Models\User;
use Illuminate\Console\Command;

class PauseKiokuConciergePilotCommand extends Command
{
    protected $signature = 'kioku:letters:pilot:pause
        {userId : users.id}
        {--note= : Reason}';

    protected $description = 'Pause an active Kioku daily pilot schedule';

    public function handle(KiokuConciergePilotService $pilot): int
    {
        $user = User::query()->find((int) $this->argument('userId'));
        if ($user === null) {
            $this->error("User [{$this->argument('userId')}] not found.");

            return self::FAILURE;
        }

        try {
            $schedule = $pilot->pause($user, (string) ($this->option('note') ?? ''));
        } catch (KiokuLetterException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Schedule paused ({$schedule->pause_reason}).");

        return self::SUCCESS;
    }
}
