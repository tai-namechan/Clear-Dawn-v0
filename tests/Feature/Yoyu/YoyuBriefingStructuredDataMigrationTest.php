<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class YoyuBriefingStructuredDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_structured_data_column_is_nullable_json_and_preserves_body(): void
    {
        $user = User::factory()->create();
        $briefing = YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => '2026-07-11',
            'body' => '既存bodyを保持',
            'status' => 'ready',
            'structured_data' => null,
        ]);

        $this->assertTrue(Schema::hasColumn('yoyu_briefings', 'structured_data'));
        $this->assertTrue(Schema::hasColumn('yoyu_briefings', 'generation_id'));
        $this->assertSame('既存bodyを保持', $briefing->fresh()->body);
        $this->assertNull($briefing->fresh()->structured_data);

        $payload = [
            'schema_version' => 2,
            'generation' => ['status' => 'generated', 'overview' => '保存'],
        ];
        $briefing->update(['structured_data' => $payload, 'generation_id' => 'gen-mig']);

        $fresh = $briefing->fresh();
        $this->assertSame('既存bodyを保持', $fresh->body);
        $this->assertSame(2, $fresh->structured_data['schema_version']);
        $this->assertSame('gen-mig', $fresh->generation_id);

        // Raw DB round-trip (JSON compatibility on sqlite/mysql).
        $raw = DB::table('yoyu_briefings')->where('id', $briefing->id)->value('structured_data');
        $this->assertNotNull($raw);
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        $this->assertSame('保存', $decoded['generation']['overview']);
    }

    public function test_generation_id_migration_rollback_and_reapply(): void
    {
        $this->assertTrue(Schema::hasColumn('yoyu_briefings', 'generation_id'));

        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_07_12_074632_add_generation_id_to_yoyu_briefings_table.php', '--force' => true]);
        $this->assertFalse(Schema::hasColumn('yoyu_briefings', 'generation_id'));

        Artisan::call('migrate', ['--path' => 'database/migrations/2026_07_12_074632_add_generation_id_to_yoyu_briefings_table.php', '--force' => true]);
        $this->assertTrue(Schema::hasColumn('yoyu_briefings', 'generation_id'));
    }

    public function test_structured_data_migration_rollback_and_reapply_keeps_body_rows(): void
    {
        $user = User::factory()->create();
        $briefing = YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => '2026-07-12',
            'body' => 'rollback-safe',
            'status' => 'ready',
            'structured_data' => ['schema_version' => 2],
        ]);

        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_07_12_065425_add_structured_data_to_yoyu_briefings_table.php', '--force' => true]);
        $this->assertFalse(Schema::hasColumn('yoyu_briefings', 'structured_data'));
        $this->assertSame('rollback-safe', DB::table('yoyu_briefings')->where('id', $briefing->id)->value('body'));

        Artisan::call('migrate', ['--path' => 'database/migrations/2026_07_12_065425_add_structured_data_to_yoyu_briefings_table.php', '--force' => true]);
        $this->assertTrue(Schema::hasColumn('yoyu_briefings', 'structured_data'));
        $this->assertSame('rollback-safe', DB::table('yoyu_briefings')->where('id', $briefing->id)->value('body'));
        $this->assertNull(DB::table('yoyu_briefings')->where('id', $briefing->id)->value('structured_data'));
    }
}
