<?php

namespace App\Domain\Yoyu\Support;

use App\Models\User;
use DateTimeZone;
use Exception;

/**
 * Phase 1: users have no timezone column — use config('app.timezone') with UTC fallback.
 */
final class UserTimezoneResolver
{
    public function for(?User $user = null): string
    {
        unset($user); // reserved for a future users.timezone column

        $candidate = config('app.timezone', 'UTC');
        if (! is_string($candidate) || $candidate === '') {
            return 'UTC';
        }

        return $this->validate($candidate) ?? 'UTC';
    }

    public function validate(string $timezone): ?string
    {
        try {
            new DateTimeZone($timezone);

            return $timezone;
        } catch (Exception) {
            return null;
        }
    }
}
