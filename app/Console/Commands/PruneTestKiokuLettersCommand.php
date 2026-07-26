<?php

namespace App\Console\Commands;

use App\Domain\Kioku\KiokuLetterMode;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Models\User;
use Illuminate\Console\Command;

class PruneTestKiokuLettersCommand extends Command
{
    protected $signature = 'kioku:letters:test:prune
        {userId? : Limit deletion to a users.id}
        {--expired-only : Only delete expired test letters}
        {--letter= : Delete a single test letter id}';

    protected $description = 'Delete mode=test Kioku letters for a user (live letters are never touched)';

    public function handle(): int
    {
        $userId = $this->argument('userId');

        if ($userId === null && ! $this->option('expired-only')) {
            $this->error('Provide userId or use --expired-only for the global scheduled prune.');

            return self::FAILURE;
        }

        if ($userId !== null && ! User::query()->whereKey((int) $userId)->exists()) {
            $this->error("User [{$userId}] not found.");

            return self::FAILURE;
        }

        $query = KiokuLetter::query()
            ->withoutUserScope()
            ->where('mode', KiokuLetterMode::Test->value)
            ->when($userId !== null, fn ($builder) => $builder->where('user_id', (int) $userId));

        if ($this->option('letter')) {
            $query->whereKey((string) $this->option('letter'));
        } elseif ($this->option('expired-only')) {
            $query->whereNotNull('test_expires_at')->where('test_expires_at', '<=', now());
        }

        $count = $query->count();
        $query->each(fn (KiokuLetter $letter) => $letter->delete());

        $scope = $userId === null ? 'all users' : "user {$userId}";
        $this->info("Deleted {$count} test letter(s) for {$scope}.");

        return self::SUCCESS;
    }
}
