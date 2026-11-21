<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Passwords the faked Have I Been Pwned API reports as leaked.
     *
     * @var list<string>
     */
    protected array $pwnedPasswords = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
        $this->fakePwnedPasswordsApi();
    }

    /**
     * Answer Password::uncompromised() lookups locally, the same way the real
     * k-anonymity range API does, so the production password rules run in
     * tests without leaving the machine.
     */
    protected function fakePwnedPasswordsApi(): void
    {
        Http::fake([
            'api.pwnedpasswords.com/range/*' => function (Request $request) {
                $hashPrefix = strtoupper(Str::afterLast($request->url(), '/'));

                $body = collect($this->pwnedPasswords)
                    ->map(fn (string $password): string => strtoupper(sha1($password)))
                    ->filter(fn (string $hash): bool => str_starts_with($hash, $hashPrefix))
                    ->map(fn (string $hash): string => substr($hash, 5).':1000')
                    ->implode("\n");

                return Http::response($body);
            },
        ]);
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
