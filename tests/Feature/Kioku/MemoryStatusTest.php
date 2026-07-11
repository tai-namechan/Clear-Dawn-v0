<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemoryStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_memory_status(): void
    {
        $this->getJson(route('kioku.memories.status', [
            'ids' => [(string) Str::ulid()],
        ]))->assertUnauthorized();
    }

    public function test_returns_only_own_memory_statuses(): void
    {
        $user = User::factory()->create();
        $captured = Memory::factory()->captured()->create(['user_id' => $user->id]);
        $enriching = Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'enriching',
            'raw_content' => 'secret-own',
        ]);
        $ready = Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'raw_content' => 'ready-own',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('kioku.memories.status', [
                'ids' => [$captured->id, $enriching->id, $ready->id],
            ]))
            ->assertOk()
            ->assertJsonPath('data.'.$captured->id, 'captured')
            ->assertJsonPath('data.'.$enriching->id, 'enriching')
            ->assertJsonPath('data.'.$ready->id, 'ready')
            ->assertJsonPath('missing_ids', []);

        $payload = $response->json();
        $this->assertArrayNotHasKey('raw_content', $payload);
        $this->assertSame(
            ['data', 'missing_ids'],
            array_keys($payload),
        );

        foreach ($payload['data'] as $status) {
            $this->assertIsString($status);
        }

        $encoded = $response->getContent();
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('secret-own', $encoded);
        $this->assertStringNotContainsString('ready-own', $encoded);
        $this->assertStringNotContainsString('raw_content', $encoded);
        $this->assertStringNotContainsString('summary', $encoded);
        $this->assertStringNotContainsString('structured_data', $encoded);
    }

    public function test_other_users_ids_are_missing_without_leaking_existence(): void
    {
        $user = User::factory()->create();
        $own = Memory::factory()->captured()->create(['user_id' => $user->id]);
        $other = Memory::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'ready',
            'raw_content' => 'other-secret',
            'title' => '他人の記憶タイトル',
        ]);
        $unknown = (string) Str::ulid();

        $response = $this->actingAs($user)
            ->getJson(route('kioku.memories.status', [
                'ids' => [$own->id, $other->id, $unknown],
            ]))
            ->assertOk()
            ->assertJsonPath('data.'.$own->id, 'captured');

        $payload = $response->json();
        $this->assertArrayNotHasKey($other->id, $payload['data']);
        $this->assertEqualsCanonicalizing(
            [$other->id, $unknown],
            $payload['missing_ids'],
        );

        $encoded = $response->getContent();
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('other-secret', $encoded);
        $this->assertStringNotContainsString('他人の記憶タイトル', $encoded);
        $this->assertStringNotContainsString('"ready"', $encoded);
    }

    public function test_validation_rejects_more_than_fifty_ids(): void
    {
        $user = User::factory()->create();
        $ids = [];

        for ($i = 0; $i < 51; $i++) {
            $ids[] = (string) Str::ulid();
        }

        $this->actingAs($user)
            ->getJson(route('kioku.memories.status', ['ids' => $ids]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    }

    public function test_validation_rejects_invalid_and_duplicate_ids(): void
    {
        $user = User::factory()->create();
        $id = (string) Str::ulid();

        $this->actingAs($user)
            ->getJson(route('kioku.memories.status', [
                'ids' => ['not-a-ulid'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);

        $this->actingAs($user)
            ->getJson(route('kioku.memories.status', [
                'ids' => [$id, $id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.1']);
    }

    public function test_status_query_count_is_stable(): void
    {
        $user = User::factory()->create();
        $memories = Memory::factory()
            ->count(5)
            ->captured()
            ->create(['user_id' => $user->id]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)
            ->getJson(route('kioku.memories.status', [
                'ids' => $memories->pluck('id')->all(),
            ]))
            ->assertOk();

        $queries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "memories"',
            ) || str_contains(
                strtolower($query['query']),
                'from `memories`',
            ));

        $this->assertCount(1, $queries);
    }
}
