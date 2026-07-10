<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Inertia SSR may POST to Vite hot URL when public/hot exists; disable in tests
        // so Http::preventStrayRequests() does not treat it as a stray outbound call.
        config(['inertia.ssr.enabled' => false]);

        Http::preventStrayRequests();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Build an Http::fake URL pattern from config/ai.php (no hardcoded hosts).
     */
    protected function anthropicFakePattern(): string
    {
        $host = parse_url((string) config('ai.anthropic.base_url'), PHP_URL_HOST);

        return ($host ?: 'api.anthropic.com').'/*';
    }
}
