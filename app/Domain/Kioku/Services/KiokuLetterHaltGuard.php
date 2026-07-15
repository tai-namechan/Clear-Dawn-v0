<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\Models\KiokuLetter;

/**
 * Blocks all AI letter generation for an owner while an unresolved
 * sensitive_leak halt exists (docs/product/kioku-concierge-daily-pilot.md §4).
 * There is intentionally no --force bypass.
 */
final class KiokuLetterHaltGuard
{
    public function assertGenerationAllowed(int $userId): void
    {
        if ($this->hasUnresolvedHalt($userId)) {
            throw new KiokuLetterException(
                "User {$userId} has an unresolved sensitive_leak halt. "
                .'Run kioku:letters:resolve-halt before generating again. No AI call was made.'
            );
        }
    }

    public function hasUnresolvedHalt(int $userId): bool
    {
        return KiokuLetter::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('status', KiokuLetter::STATUS_HALTED)
            ->whereNull('halt_resolved_at')
            ->exists();
    }
}
