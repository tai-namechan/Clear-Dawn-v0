<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Services\KiokuLetterCandidateService;
use App\Domain\Kioku\Services\KiokuLetterGenerator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;

/**
 * Manual weekly letter generation for the 4-week concierge experiment
 * (docs/product/kioku-final-remaining-implementation.md §14). There is no
 * cron or auto-delivery: a human runs this once per week at a fixed time.
 */
class GenerateKiokuLetterCommand extends Command
{
    protected $signature = 'kioku:letters:generate
        {userId : users.id to generate the letter for}
        {--character= : shiori or nagi (default: kioku.concierge.default_character)}
        {--context= : This week\'s manual context passed to the AI}
        {--week= : Any date inside the target week (normalized to Monday; default: current week)}
        {--dry-run : Show candidate counts and exclusion breakdown without calling the AI}';

    protected $description = 'Generate the weekly Kioku concierge letter for one user (max 5 items, 0 allowed)';

    public function handle(
        KiokuLetterCandidateService $candidates,
        KiokuLetterGenerator $generator,
    ): int {
        if (! config('kioku.concierge.enabled')) {
            $this->error('KIOKU_CONCIERGE_ENABLED is false — enable it for this environment first.');

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
            $weekStart = ($this->option('week') !== null
                ? CarbonImmutable::parse((string) $this->option('week'))
                : CarbonImmutable::now())->startOfWeek();
        } catch (InvalidFormatException) {
            $this->error("Could not parse --week [{$this->option('week')}]. Pass a date like 2026-07-13.");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            return $this->reportDryRun($candidates, (int) $user->id, $weekStart);
        }

        try {
            $letter = $generator->generate($user, $weekStart, $character, $this->option('context'));
        } catch (KiokuLetterException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Letter {$letter->id} for week {$weekStart->toDateString()} is {$letter->status}.");
        $this->line("character: {$letter->character_variant} / candidates: {$letter->candidate_count} / items: {$letter->item_count}");

        if ($letter->status === KiokuLetter::STATUS_EMPTY) {
            $this->comment('0 items is a valid outcome — no forced regeneration (max 5, zero allowed).');
        }

        return self::SUCCESS;
    }

    private function reportDryRun(
        KiokuLetterCandidateService $candidates,
        int $userId,
        CarbonImmutable $weekStart,
    ): int {
        $breakdown = $candidates->exclusionBreakdown($userId);

        $this->info("Dry run for user {$userId} / week {$weekStart->toDateString()} — no AI call, no letter row.");
        $this->table(
            ['exclusion', 'count'],
            [
                ['total memories', $breakdown['total']],
                ['excluded: not ready', $breakdown['not_ready']],
                ['excluded: sensitive', $breakdown['sensitive']],
                ['excluded: letter evaluation logs', $breakdown['letter_logs']],
                ['excluded: missing summary', $breakdown['missing_summary']],
                ['excluded: 14-day cooldown', $breakdown['cooling_down']],
                ['eligible', $breakdown['eligible']],
                ['sent to AI (max '.KiokuLetterCandidateService::MAX_CANDIDATES.')', $breakdown['capped']],
            ],
        );

        return self::SUCCESS;
    }
}
