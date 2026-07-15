<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\Services\KiokuConciergePilotService;
use App\Models\User;
use Illuminate\Console\Command;

class ResumeKiokuConciergePilotCommand extends Command
{
    protected $signature = 'kioku:letters:pilot:resume
        {userId : users.id}
        {--note= : Confirmation note}';

    protected $description = 'Resume a paused Kioku daily pilot schedule (not halted)';

    public function handle(KiokuConciergePilotService $pilot): int
    {
        $user = User::query()->find((int) $this->argument('userId'));
        if ($user === null) {
            $this->error("User [{$this->argument('userId')}] not found.");

            return self::FAILURE;
        }

        try {
            $schedule = $pilot->resume($user, (string) ($this->option('note') ?? ''));
        } catch (KiokuLetterException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Schedule is now {$schedule->state}.");

        return self::SUCCESS;
    }
}
