<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Services\KiokuLetterHaltResolveService;
use App\Models\User;
use Illuminate\Console\Command;

class ResolveKiokuLetterHaltCommand extends Command
{
    protected $signature = 'kioku:letters:resolve-halt
        {userId : users.id}
        {letterId : kioku_letters.id}
        {--note= : Operator confirmation note (required)}';

    protected $description = 'Resolve an unresolved sensitive_leak halt after human review (never clears Memory.sensitive)';

    public function handle(KiokuLetterHaltResolveService $resolver): int
    {
        $user = User::query()->find((int) $this->argument('userId'));
        if ($user === null) {
            $this->error("User [{$this->argument('userId')}] not found.");

            return self::FAILURE;
        }

        $letter = KiokuLetter::query()->withoutUserScope()->find((string) $this->argument('letterId'));
        if ($letter === null) {
            $this->error("Letter [{$this->argument('letterId')}] not found.");

            return self::FAILURE;
        }

        $note = (string) ($this->option('note') ?? '');

        try {
            $resolved = $resolver->resolve($user, $letter, $note);
        } catch (KiokuLetterException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Halt resolved for letter {$resolved->id} at {$resolved->halt_resolved_at}.");
        $this->comment('Memory.sensitive was NOT cleared. Use a separate audited repair if a false positive must be undone.');

        return self::SUCCESS;
    }
}
