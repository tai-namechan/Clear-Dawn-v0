<?php

namespace App\Console\Commands;

use App\Domain\Yoyu\Money\Services\RecurringCashflowGenerator;
use App\Models\User;
use Illuminate\Console\Command;

class GenerateYoyuMoneyRecurringCommand extends Command
{
    protected $signature = 'yoyu-money:generate-recurring {--user= : Limit generation to a single user id}';

    protected $description = 'Generate upcoming Yoyu Money recurring cashflows (optional maintenance hook)';

    public function handle(RecurringCashflowGenerator $generator): int
    {
        $userId = $this->option('user');
        $created = 0;

        if ($userId !== null && $userId !== '') {
            $user = User::query()->find($userId);
            if ($user === null) {
                $this->error("User {$userId} not found.");

                return self::FAILURE;
            }

            $created = $generator->generateForUser($user);
            $this->info("Created {$created} cashflow(s) for user {$userId}.");

            return self::SUCCESS;
        }

        User::query()
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($generator, &$created): void {
                foreach ($users as $user) {
                    $created += $generator->generateForUser($user);
                }
            });

        $this->info("Created {$created} cashflow(s) across users.");

        return self::SUCCESS;
    }
}
