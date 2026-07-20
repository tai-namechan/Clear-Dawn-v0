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

        Carbon::setTestNow();
    }

    public function test_null_user_timezone_falls_back_to_asia_tokyo_not_utc(): void
    {
        // app.timezone が UTC でも、未設定ユーザーは製品既定の Asia/Tokyo。
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-07-17 20:00:00', 'UTC'));

        $user = new User(['timezone' => null]);
        $resolver = new UserTimezoneResolver;

        $this->assertSame('Asia/Tokyo', $resolver->for($user));
        $this->assertSame('Asia/Tokyo', $resolver->for(null));
        $this->assertSame('2026-07-18', $resolver->todayDateString($user));
        $this->assertSame('2026-07-18', $resolver->todayDateString(null));

        Carbon::setTestNow();
    }

    public function test_invalid_user_timezone_falls_back_to_asia_tokyo(): void
    {
        config(['app.timezone' => 'UTC']);

        $user = new User(['timezone' => 'Not/AZone']);
        $resolver = new UserTimezoneResolver;

        $this->assertSame('Asia/Tokyo', $resolver->for($user));
    }
}
