<?php

namespace Tests\Unit\Yoyu\Support;

use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserTimezoneResolverTest extends TestCase
{
    public function test_today_date_string_uses_user_timezone(): void
    {
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-07-17 20:00:00', 'UTC'));

        $user = new User(['timezone' => 'Asia/Tokyo']);
        $resolver = new UserTimezoneResolver;

        $this->assertSame('Asia/Tokyo', $resolver->for($user));
        $this->assertSame('2026-07-18', $resolver->todayDateString($user));
        $this->assertSame('2026-07-17', $resolver->todayDateString(null));

        Carbon::setTestNow();
    }

    public function test_invalid_user_timezone_falls_back_to_utc(): void
    {
        config(['app.timezone' => 'UTC']);

        $user = new User(['timezone' => 'Not/AZone']);
        $resolver = new UserTimezoneResolver;

        $this->assertSame('UTC', $resolver->for($user));
    }
}
