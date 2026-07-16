<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemoryTagSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_by_tags_alone_with_exact_element_match(): void
    {
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'ヨガメモ',
            'tags' => ['ヨガ'],
            'status' => 'ready',
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'ヨガ教室メモ',
            'tags' => ['ヨガ教室'],
            'status' => 'ready',
        ]);
        Memory::factory()->create([
            'user_id' => User::factory()->create()->id,
            'title' => '他人のヨガ',
            'tags' => ['ヨガ'],
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->get(route('kioku.home', ['tags' => ['ヨガ']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kioku/Index')
                ->has('memories', 1)
                ->where('memories.0.title', 'ヨガメモ')
                ->where('filters.tags', ['ヨガ'])
                ->where('filters.tag_mode', 'and')
            );
    }

    public function test_and_requires_all_tags_or_requires_any(): void
    {
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '両方',
            'tags' => ['ヨガ', '仕事'],
            'status' => 'ready',
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'ヨガのみ',
            'tags' => ['ヨガ'],
            'status' => 'ready',
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '仕事のみ',
            'tags' => ['仕事'],
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->get(route('kioku.home', [
                'tags' => ['ヨガ', '仕事'],
                'tag_mode' => 'and',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('memories', 1)
                ->where('memories.0.title', '両方')
            );

        $this->actingAs($user)
            ->get(route('kioku.home', [
                'tags' => ['ヨガ', '仕事'],
                'tag_mode' => 'or',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('memories', 3)
                ->where('filters.tag_mode', 'or')
            );
    }

    public function test_combines_q_types_and_tags(): void
    {
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'Vite エラー',
            'raw_content' => 'manifest',
            'memory_type' => 'error_log',
            'tags' => ['Vite', '仕事'],
            'status' => 'ready',
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'Vite 学び',
            'raw_content' => 'tutorial',
            'memory_type' => 'learning',
            'tags' => ['Vite'],
            'status' => 'ready',
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '別のエラー',
            'raw_content' => 'other',
            'memory_type' => 'error_log',
            'tags' => ['仕事'],
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->get(route('kioku.home', [
                'q' => 'Vite',
                'types' => ['error_log'],
                'tags' => ['仕事'],
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('memories', 1)
                ->where('memories.0.title', 'Vite エラー')
                ->where('filters.q', 'Vite')
                ->where('filters.types', ['error_log'])
                ->where('filters.tags', ['仕事'])
            );
    }

    public function test_invalid_tag_mode_and_empty_tags_do_not_500(): void
    {
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '通常',
            'tags' => ['通常'],
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->get(route('kioku.home', [
                'tags' => ['通常'],
                'tag_mode' => 'xor',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.tag_mode', 'and')
                ->has('memories', 1)
            );

        $this->actingAs($user)
            ->get(route('kioku.home', [
                'tags' => [],
                'tag_mode' => ['or'],
                'q' => ['配列は無視'],
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.tag_mode', 'and')
                ->where('filters.q', null)
            );

        $this->actingAs($user)
            ->get(route('kioku.home', ['tags' => '通常']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('memories', 1)
                ->where('filters.tags', ['通常'])
            );
    }

    public function test_existing_q_and_types_search_still_works(): void
    {
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '転職メモ',
            'raw_content' => 'ポートフォリオ',
            'memory_type' => 'thought',
            'tags' => ['キャリア'],
            'status' => 'ready',
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'イベント',
            'raw_content' => 'イベント',
            'memory_type' => 'event',
            'tags' => ['キャリア'],
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->get(route('kioku.home', [
                'q' => 'ポートフォリオ',
                'types' => ['thought'],
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('memories', 1)
                ->where('memories.0.title', '転職メモ')
                ->where('filters.tags', [])
                ->where('filters.tag_mode', 'and')
            );
    }
}
