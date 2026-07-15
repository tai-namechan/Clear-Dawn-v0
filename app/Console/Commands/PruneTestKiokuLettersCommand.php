<?php

namespace App\Console\Commands;

use App\Domain\Kioku\KiokuLetterMode;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Models\User;
use Illuminate\Console\Command;

class PruneTestKiokuLettersCommand extends Command
{
    protected $signature = 'kioku:letters:test:prune
        {userId : users.id}
        {--expired-only : Only delete expired test letters}
        {--letter= : Delete a single test letter id}';

    protected $description = 'Delete mode=test Kioku letters for a user (live letters are never touched)';

    public function handle(): int
    {
        $user = User::query()->find((int) $this->argument('userId'));
        if ($user === null) {
            $this->error("User [{$this->argument('userId')}] not found.");

            return self::FAILURE;
        }

        $query = KiokuLetter::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('mode', KiokuLetterMode::Test->value);

        if ($this->option('letter')) {
            $query->whereKey((string) $this->option('letter'));
        } elseif ($this->option('expired-only')) {
            $query->whereNotNull('test_expires_at')->where('test_expires_at', '<=', now());
        }

        $count = $query->count();
        $query->each(fn (KiokuLetter $letter) => $letter->delete());

        $this->info("Deleted {$count} test letter(s) for user {$user->id}.");

        return self::SUCCESS;
    }
}
