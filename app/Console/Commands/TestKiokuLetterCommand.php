<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\KiokuLetterCadence;
use App\Domain\Kioku\KiokuLetterMode;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Services\KiokuLetterGenerator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class TestKiokuLetterCommand extends Command
{
    protected $signature = 'kioku:letters:test
        {userId : users.id}
        {--character= : shiori or nagi}
        {--context= : Optional context}
        {--confirm-production : Required when APP_ENV=production}';

    protected $description = 'Generate a mode=test concierge letter with real candidates + AI (does not consume live dedupe slots)';

    public function handle(KiokuLetterGenerator $generator): int
    {
        if (! config('kioku.concierge.enabled')) {
            $this->error('KIOKU_CONCIERGE_ENABLED is false.');

            return self::FAILURE;
        }

        if (! config('kioku.concierge.test_enabled')) {
            $this->error('KIOKU_CONCIERGE_TEST_ENABLED is false.');

            return self::FAILURE;
        }

        if (app()->environment('production') && ! $this->option('confirm-production')) {
            $this->error('Production requires --confirm-production.');

            return self::FAILURE;
        }

        $user = User::query()->find((int) $this->argument('userId'));
        if ($user === null) {
            $this->error("User [{$this->argument('userId')}] not found.");

            return self::FAILURE;
        }

        $character = (string) ($this->option('character')
            ?? config('kioku.concierge.default_character', 'shiori'));
        if (! in_array($character, KiokuLetter::CHARACTER_VARIANTS, true)) {
            $this->error("Unknown character [{$character}]. Use shiori or nagi.");

            return self::FAILURE;
        }

        try {
            $letter = $generator->generateLetter(
                user: $user,
                characterVariant: $character,
                context: $this->option('context'),
                mode: KiokuLetterMode::Test,
                cadence: KiokuLetterCadence::Weekly,
                deliveryDate: CarbonImmutable::now()->startOfDay(),
            );
        } catch (KiokuLetterException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $url = url('/kioku/letters/'.$letter->id);
        $this->info("Test letter {$letter->id} is {$letter->status}.");
        $this->line("Owner-only URL: {$url}");
        $this->comment('Test letters do not affect live dedupe, cooldown, or experiment metrics.');

        return self::SUCCESS;
    }
}
