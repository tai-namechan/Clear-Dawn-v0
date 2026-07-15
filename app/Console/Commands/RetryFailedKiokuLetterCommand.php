<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Services\KiokuLetterGenerator;
use App\Models\User;
use Illuminate\Console\Command;

class RetryFailedKiokuLetterCommand extends Command
{
    protected $signature = 'kioku:letters:retry-failed
        {userId : users.id}
        {letterId : kioku_letters.id of a failed letter}
        {--context= : Optional context override for the retry}';

    protected $description = 'Retry a failed Kioku letter in-place (failed only; never overwrites published/empty/halted)';

    public function handle(KiokuLetterGenerator $generator): int
    {
        if (! config('kioku.concierge.enabled')) {
            $this->error('KIOKU_CONCIERGE_ENABLED is false.');

            return self::FAILURE;
        }

        $user = User::query()->find((int) $this->argument('userId'));
        if ($user === null) {
            $this->error("User [{$this->argument('userId')}] not found.");

            return self::FAILURE;
        }

        $letter = KiokuLetter::query()->withoutUserScope()->find((string) $this->argument('letterId'));
        if ($letter === null || (int) $letter->user_id !== (int) $user->id) {
            $this->error('Letter not found for this user.');

            return self::FAILURE;
        }

        try {
            $retried = $generator->retryFailed($letter, $this->option('context'));
        } catch (KiokuLetterException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Letter {$retried->id} retry finished with status {$retried->status} (retry_count={$retried->retry_count}).");

        return self::SUCCESS;
    }
}
