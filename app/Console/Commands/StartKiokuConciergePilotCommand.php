<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\Services\KiokuConciergePilotService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;

class StartKiokuConciergePilotCommand extends Command
{
    protected $signature = 'kioku:letters:pilot:start
        {userId : users.id}
        {--start= : Pilot start date YYYY-MM-DD}
        {--days=14 : Number of pilot days}
        {--time=21:00 : Local delivery time HH:MM}
        {--timezone=Asia/Tokyo : IANA timezone}
        {--send-now : Generate today\'s letter if inside the window and not yet created}
        {--dry-run : Show the schedule plan without writing}
        {--confirm-production : Required when APP_ENV=production}';

    protected $description = 'Create/activate a 14-day daily Kioku letter pilot schedule (dates from args, never hard-coded)';

    public function handle(KiokuConciergePilotService $pilot): int
    {
        if (! config('kioku.concierge.enabled')) {
            $this->error('KIOKU_CONCIERGE_ENABLED is false.');

            return self::FAILURE;
        }

        if (app()->environment('production')
            && ($this->option('send-now') || ! $this->option('dry-run'))
            && ! $this->option('confirm-production')
        ) {
            $this->error('Production start/send-now requires --confirm-production.');

            return self::FAILURE;
        }

        $user = User::query()->find((int) $this->argument('userId'));
        if ($user === null) {
            $this->error("User [{$this->argument('userId')}] not found.");

            return self::FAILURE;
        }

        if ($this->option('start') === null) {
            $this->error('--start=YYYY-MM-DD is required.');

            return self::FAILURE;
        }

        try {
            $start = CarbonImmutable::parse((string) $this->option('start'));
        } catch (InvalidFormatException) {
            $this->error("Could not parse --start [{$this->option('start')}].");

            return self::FAILURE;
        }

        try {
            $result = $pilot->start(
                user: $user,
                startDate: $start,
                days: (int) $this->option('days'),
                time: (string) $this->option('time'),
                timezone: (string) $this->option('timezone'),
                sendNow: (bool) $this->option('send-now'),
                dryRun: (bool) $this->option('dry-run'),
            );
        } catch (KiokuLetterException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $schedule = $result['schedule'];
        $prefix = $this->option('dry-run') ? '[dry-run] ' : '';
        $this->info("{$prefix}Pilot schedule for user {$user->id}: {$schedule->state}");
        $this->line("window: {$schedule->pilot_start_date?->toDateString()} .. {$schedule->pilot_end_date?->toDateString()} ({$schedule->pilot_days} days)");
        $this->line("delivery: {$schedule->daily_delivery_time} {$schedule->timezone}");
        $this->line('next_delivery_at: '.($schedule->next_delivery_at?->toIso8601String() ?? 'null'));

        if ($result['letter'] !== null) {
            $this->info("send-now letter {$result['letter']->id} => {$result['letter']->status}");
        }

        return self::SUCCESS;
    }
}
