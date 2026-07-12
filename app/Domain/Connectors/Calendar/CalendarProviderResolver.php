<?php

namespace App\Domain\Connectors\Calendar;

use App\Domain\Kioku\Models\Connector;
use App\Models\User;

final class CalendarProviderResolver
{
    public function for(User $user): CalendarProvider
    {
        if ($this->mockEnabled()) {
            return new MockCalendarProvider;
        }

        $connector = Connector::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('source_type', Connector::SOURCE_GOOGLE_CALENDAR)
            ->first();

        if ($connector === null) {
            return new EmptyCalendarProvider;
        }

        return new CachedGoogleCalendarProvider($connector);
    }

    /**
     * Mock is opt-in via config AND restricted to local/testing.
     * Staging/production never auto-fall back to fabricated events.
     */
    private function mockEnabled(): bool
    {
        return config('calendar.driver') === 'mock'
            && app()->environment(['local', 'testing']);
    }
}
