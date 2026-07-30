<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\IosShortcut\KiokuCaptureTokenService;
use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Models\KiokuCaptureToken;
use App\Domain\Kioku\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

class IosShortcutCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kioku.ios_shortcut.enabled' => true]);
    }

    public function test_text_capture_via_scoped_token(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        $issued = app(KiokuCaptureTokenService::class)->issue($user, 'iPhone');

        $this->postJson('/api/kioku/captures', [
            'kind' => 'text',
            'text' => 'ショートカットからの一文',
        ], [
            'Authorization' => 'Bearer '.$issued['plain'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('message', '原情報を保存しました')
            ->assertJsonMissingPath('token');

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $this->assertSame('ios_shortcut', $memory->capture_channel);
        $this->assertSame('text', $memory->raw_kind);
    }

    public function test_revoked_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $service = app(KiokuCaptureTokenService::class);
        $issued = $service->issue($user, 'iPhone');
        $service->revoke($user, $issued['token']->id);

        $this->postJson('/api/kioku/captures', [
            'kind' => 'text',
            'text' => '失効後',
        ], [
            'Authorization' => 'Bearer '.$issued['plain'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertUnauthorized();
    }

    public function test_flag_off_returns_not_found(): void
    {
        config(['kioku.ios_shortcut.enabled' => false]);
        $user = User::factory()->create();
        $issued = app(KiokuCaptureTokenService::class)->issue($user, 'iPhone');

        $this->postJson('/api/kioku/captures', [
            'kind' => 'text',
            'text' => 'off',
        ], [
            'Authorization' => 'Bearer '.$issued['plain'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    public function test_token_hash_is_stored_not_plain(): void
    {
        $user = User::factory()->create();
        $issued = app(KiokuCaptureTokenService::class)->issue($user, 'Watch');

        $row = KiokuCaptureToken::query()->withoutUserScope()->sole();
        $this->assertSame(hash('sha256', $issued['plain']), $row->token_hash);
        $this->assertStringNotContainsString($issued['plain'], json_encode($row->toArray()) ?: '');
    }
}
