<?php

namespace App\Domain\Yoyu\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Exception;

/**
 * Resolve a user's IANA timezone.
 *
 * Fallback is Asia/Tokyo (Clear Dawn product default), not Laravel's UTC app.timezone.
 * Money / self-management / records share this resolver for calendar "today".
 */
final class UserTimezoneResolver
{
    public const DEFAULT_TIMEZONE = 'Asia/Tokyo';

    public function for(?User $user = null): string
    {
        if ($user !== null && is_string($user->timezone) && $user->timezone !== '') {
            return $this->validate($user->timezone) ?? self::DEFAULT_TIMEZONE;
        }

        return self::DEFAULT_TIMEZONE;
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
