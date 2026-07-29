<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Models\KiokuActionExport;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Yoyu\Models\YoyuTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class MemoryExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'kioku.obsidian_export.enabled' => true,
            'kioku.action_export.enabled' => true,
        ]);
    }

    public function test_obsidian_zip_excludes_other_users_and_sensitive_by_default(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'sensitive' => false,
            'title' => '自分の記憶',
            'summary' => 'export me',
            'tags' => ['A'],
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'sensitive' => true,
            'title' => '秘密',
            'summary' => 'no',
        ]);
        Memory::factory()->create([
            'user_id' => $other->id,
            'status' => 'ready',
            'title' => '他人',
            'summary' => 'leak',
        ]);

        $response = $this->actingAs($user)
            ->get(route('kioku.export.obsidian'))
            ->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($tmp, $response->streamedContent());
        $zip = new ZipArchive;
        $zip->open($tmp);
        $bodies = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $bodies .= $zip->getFromIndex($i)."\n";
        }
        $zip->close();
        @unlink($tmp);

        $this->assertStringContainsString('自分の記憶', $bodies);
        $this->assertStringContainsString('export me', $bodies);
        $this->assertStringNotContainsString('秘密', $bodies);
        $this->assertStringNotContainsString('leak', $bodies);
    }

    public function test_send_to_yoyu_is_idempotent_and_owner_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'sensitive' => false,
            'title' => '行動化したい',
        ]);

        $this->actingAs($other)
            ->post(route('kioku.memories.export.yoyu', $memory))
            ->assertNotFound();

        $this->actingAs($user)
            ->post(route('kioku.memories.export.yoyu', $memory))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('kioku.memories.export.yoyu', $memory))
            ->assertRedirect();

        $this->assertSame(1, YoyuTask::query()->withoutUserScope()->where('user_id', $user->id)->count());
        $this->assertSame(1, KiokuActionExport::query()->withoutUserScope()->where('memory_id', $memory->id)->count());
    }

    public function test_flag_off_blocks_exports(): void
    {
        config([
            'kioku.obsidian_export.enabled' => false,
            'kioku.action_export.enabled' => false,
        ]);
        $user = User::factory()->create();
        $memory = Memory::factory()->create(['user_id' => $user->id, 'status' => 'ready']);

        $this->actingAs($user)->get(route('kioku.export.obsidian'))->assertNotFound();
        $this->actingAs($user)->post(route('kioku.memories.export.yoyu', $memory))->assertNotFound();
    }
}
