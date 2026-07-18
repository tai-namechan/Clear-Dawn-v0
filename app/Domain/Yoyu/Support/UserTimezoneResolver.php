<?php

namespace App\Domain\Yoyu\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Exception;

/**
 * Resolve a user's IANA timezone, falling back to app config then UTC.
 */
final class UserTimezoneResolver
{
    public function for(?User $user = null): string
    {
        if ($user !== null && is_string($user->timezone) && $user->timezone !== '') {
            return $this->validate($user->timezone) ?? 'UTC';
        }

        $candidate = config('app.timezone', 'UTC');
        if (! is_string($candidate) || $candidate === '') {
            return 'UTC';
        }

        return $this->validate($candidate) ?? 'UTC';
    }

    /**
     * Calendar "today" for the user (date-only Y-m-d in their timezone).
     */
    public function todayDateString(?User $user = null): string
    {
        return CarbonImmutable::now($this->for($user))->toDateString();
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
