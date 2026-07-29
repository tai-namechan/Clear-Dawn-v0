<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Capture\CaptureChannel;
use App\Domain\Kioku\Capture\RawKind;
use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class CapturePipelineBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_capture_sets_raw_kind_and_channel(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.manual'), [
                'client_capture_id' => (string) Str::uuid(),
                'raw_content' => '境界共通化の一文',
            ])
            ->assertCreated();

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $this->assertSame(RawKind::Text->value, $memory->raw_kind);
        $this->assertSame(CaptureChannel::WebText->value, $memory->capture_channel);
        $this->assertSame('manual', $memory->source_type);
        Bus::assertDispatched(EnrichMemoryJob::class);
    }

    public function test_url_auto_detect_sets_url_raw_kind(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.manual'), [
                'client_capture_id' => (string) Str::uuid(),
                'raw_content' => 'https://example.com/note',
            ])
            ->assertCreated();

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $this->assertSame(RawKind::Url->value, $memory->raw_kind);
        $this->assertSame(CaptureChannel::WebUrl->value, $memory->capture_channel);
        $this->assertSame('url', $memory->source_type);
        $this->assertSame('https://example.com/note', $memory->raw_content);
    }

    public function test_raw_content_remains_immutable_after_pipeline_capture(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.manual'), [
                'client_capture_id' => (string) Str::uuid(),
                'raw_content' => '原文は書き換えない',
            ])
            ->assertCreated();

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->sole();

        $this->expectException(LogicException::class);
        $memory->update(['raw_content' => '壊れた原文']);
    }

    public function test_backfill_dry_run_does_not_write(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'source_type' => 'manual',
            'raw_kind' => null,
            'capture_channel' => null,
        ]);

        Artisan::call('kioku:capture:backfill-raw-kind', [
            '--dry-run' => true,
            '--user' => $user->id,
        ]);

        $memory->refresh();
        $this->assertNull($memory->raw_kind);
        $this->assertNull($memory->capture_channel);
    }

    public function test_backfill_maps_known_source_types(): void
    {
        $user = User::factory()->create();
        $manual = Memory::factory()->create([
            'user_id' => $user->id,
            'source_type' => 'manual',
            'raw_kind' => null,
            'capture_channel' => null,
        ]);
        $voice = Memory::factory()->voice()->create([
            'user_id' => $user->id,
            'raw_kind' => null,
            'capture_channel' => null,
            'status' => 'ready',
            'transcription_status' => 'ready',
            'transcript_text' => 'hello',
        ]);

        Artisan::call('kioku:capture:backfill-raw-kind', [
            '--user' => $user->id,
        ]);

        $manual->refresh();
        $voice->refresh();
        $this->assertSame('text', $manual->raw_kind);
        $this->assertSame('web_text', $manual->capture_channel);
        $this->assertSame('audio', $voice->raw_kind);
        $this->assertSame('browser_voice', $voice->capture_channel);
    }

    public function test_resolved_raw_kind_falls_back_to_source_type(): void
    {
        $memory = Memory::factory()->make([
            'source_type' => 'voice',
            'raw_kind' => null,
        ]);

        $this->assertTrue($memory->isAudioRaw());
        $this->assertSame('audio', $memory->resolvedRawKind());
    }
}
