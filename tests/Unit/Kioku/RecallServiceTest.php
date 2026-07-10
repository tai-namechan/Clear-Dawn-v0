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

    public function test_registry_has_nine_types_including_decision_fields(): void
    {
        $registry = app(MemoryTypeRegistry::class);
        $this->assertCount(9, $registry->keys());
        $fields = collect($registry->get('decision')->displayFields())->pluck('key')->all();
        $this->assertContains('options', $fields);
        $this->assertContains('review_condition', $fields);
    }
}
