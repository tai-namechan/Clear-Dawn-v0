<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Models\KiokuConciergeSchedule;
use App\Models\User;
use Illuminate\Console\Command;

class KiokuConciergePilotStatusCommand extends Command
{
    protected $signature = 'kioku:letters:pilot:status {userId : users.id}';

    protected $description = 'Show Kioku daily pilot schedule status for a user';

    public function handle(): int
    {
        $user = User::query()->find((int) $this->argument('userId'));
        if ($user === null) {
            $this->error("User [{$this->argument('userId')}] not found.");

            return self::FAILURE;
        }

        $schedule = KiokuConciergeSchedule::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->first();

        if ($schedule === null) {
            $this->warn("No schedule for user {$user->id}.");

            return self::SUCCESS;
        }

        $this->table(['field', 'value'], [
            ['state', $schedule->state],
            ['pilot_start_date', $schedule->pilot_start_date?->toDateString()],
            ['pilot_end_date', $schedule->pilot_end_date?->toDateString()],
            ['pilot_days', (string) $schedule->pilot_days],
            ['timezone', $schedule->timezone],
            ['daily_delivery_time', $schedule->daily_delivery_time],
            ['next_delivery_at', $schedule->next_delivery_at?->toIso8601String()],
            ['consecutive_unopened', (string) $schedule->consecutive_unopened],
            ['pause_reason', $schedule->pause_reason],
        ]);

        return self::SUCCESS;
    }
}
