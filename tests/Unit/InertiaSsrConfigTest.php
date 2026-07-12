<?php

namespace Tests\Unit;

use Tests\TestCase;

class InertiaSsrConfigTest extends TestCase
{
    /**
     * Prove PHPUnit disables SSR via config/env (not a TestCase override).
     * SSR bundle correctness is covered by `npm run build:ssr` in `composer ci:check`.
     */
    public function test_inertia_ssr_is_disabled_during_phpunit(): void
    {
        $this->assertFalse(config('inertia.ssr.enabled'));
    }
}
