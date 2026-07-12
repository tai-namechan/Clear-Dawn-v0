<?php

namespace Tests\Unit\Kioku;

use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\RecallService;
use App\Domain\Kioku\Types\MemoryTypeRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecallServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_returns_short_lines_and_excludes_sensitive(): void
    {
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'ヨガ欠席',
            'summary' => '仕事が詰まった日にヨガを欠席した',
            'raw_content' => '仕事が詰まった日にヨガを欠席',
            'memory_type' => 'event',
            'status' => 'ready',
            'sensitive' => false,
            'tags' => ['ヨガ'],
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '機微',
            'summary' => '秘密',
            'raw_content' => '秘密の健康情報',
            'memory_type' => 'thought',
            'status' => 'ready',
            'sensitive' => true,
        ]);

        $lines = app(RecallService::class)->for((int) $user->id, 'ヨガ 仕事', 5);

        $this->assertNotEmpty($lines);
        $this->assertTrue(collect($lines)->every(fn (string $line) => ! str_contains($line, '秘密')));
    }

    public function test_memories_excludes_other_users_sensitive_and_non_ready(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '朝ブリーフィング向け',
            'summary' => '今日の予定の振り返り',
            'raw_content' => '今日の予定について',
            'status' => 'ready',
            'sensitive' => false,
            'referenced_count' => 0,
            'captured_at' => now()->subHour(),
        ]);
        Memory::factory()->create([
            'user_id' => $other->id,
            'title' => '朝ブリーフィング他ユーザー',
            'summary' => '今日の予定の秘密',
            'raw_content' => '他ユーザーの今日の予定',
            'status' => 'ready',
            'sensitive' => false,
            'captured_at' => now(),
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '朝ブリーフィング機微',
            'summary' => '今日の予定の機微',
            'raw_content' => '機微な今日の予定',
            'status' => 'ready',
            'sensitive' => true,
            'captured_at' => now(),
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '朝ブリーフィング未整理',
            'summary' => '今日の予定ドラフト',
            'raw_content' => 'captured の今日の予定',
            'status' => 'captured',
            'sensitive' => false,
            'captured_at' => now(),
        ]);

        $memories = app(RecallService::class)->memories(
            (int) $user->id,
            '朝ブリーフィング 今日の予定',
            5,
            countReference: false,
        );

        $this->assertCount(1, $memories);
        $this->assertSame($mine->id, $memories->first()->id);
        $this->assertSame(0, $mine->fresh()->referenced_count);
    }

    public function test_memories_caps_at_five_and_count_reference_false_skips_increment(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 7; $i++) {
            Memory::factory()->create([
                'user_id' => $user->id,
                'title' => "朝ブリーフィング {$i}",
                'summary' => "今日の予定メモ {$i}",
                'raw_content' => "今日の予定 raw {$i}",
                'status' => 'ready',
                'sensitive' => false,
                'referenced_count' => 0,
                'captured_at' => now()->subMinutes($i),
            ]);
        }

        $memories = app(RecallService::class)->memories(
            (int) $user->id,
            '朝ブリーフィング 今日の予定',
            5,
            countReference: false,
        );

        $this->assertCount(5, $memories);
        $this->assertSame(0, Memory::query()->where('user_id', $user->id)->sum('referenced_count'));
    }

    public function test_registry_has_nine_types_including_decision_fields(): void
    {
        $registry = app(MemoryTypeRegistry::class);
        $this->assertCount(9, $registry->keys());
        $fields = collect($registry->get('decision')->displayFields())->pluck('key')->all();
        $this->assertContains('options', $fields);
        $this->assertContains('review_condition', $fields);
    }
}
