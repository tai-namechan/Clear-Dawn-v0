<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * N+1 detection in the local environment (audit M-2) logs instead of throwing,
 * so a production-like local check never turns a page into a 500.
 */
class LocalModelStrictnessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        Model::preventSilentlyDiscardingAttributes(false);

        parent::tearDown();
    }

    private function bootAs(string $environment): void
    {
        $this->app['env'] = $environment;
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_lazy_loading_in_local_is_logged_instead_of_thrown(): void
    {
        User::factory()->count(2)->create();
        $this->bootAs('local');
        Log::spy();

        $users = User::query()->get();
        $users->first()->lifeAreas;

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Lazy loading detected (N+1)'
                && $context === ['model' => User::class, 'relation' => 'lifeAreas'])
            ->once();
    }

    public function test_lazy_loading_is_not_flagged_outside_local(): void
    {
        User::factory()->count(2)->create();
        $this->bootAs('production');
        Log::spy();

        User::query()->get()->first()->lifeAreas;

        Log::shouldNotHaveReceived('warning');
    }
}
