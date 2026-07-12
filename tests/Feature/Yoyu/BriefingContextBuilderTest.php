<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Connectors\Calendar\CalendarConnectionStatus;
use App\Domain\Kioku\Models\Connector;
use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use App\Domain\Yoyu\Models\YoyuPlace;
use App\Domain\Yoyu\Models\YoyuTask;
use App\Domain\Yoyu\Services\BriefingContextBuilder;
use App\Enums\MatrixRowKey;
use App\Models\LifeArea;
use App\Models\MatrixCell;
use App\Models\MatrixCellItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MatrixRowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BriefingContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MatrixRowSeeder::class);
        config(['app.timezone' => 'Asia/Tokyo']);
    }

    public function test_builds_deterministic_context_with_travel_resolution(): void
    {
        $user = User::factory()->create();
        $connector = Connector::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'source_type' => Connector::SOURCE_GOOGLE_CALENDAR,
            'status' => 'connected',
            'external_account_email' => 'me@example.com',
            'access_token' => 'a',
            'refresh_token' => 'r',
            'last_synced_at' => now(),
        ]);

        $tz = 'Asia/Tokyo';
        $day = CarbonImmutable::parse('2026-07-11', $tz)->startOfDay();

        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'evt-1',
            'title' => 'ジム',
            'all_day' => false,
            'starts_at' => $day->setTime(13, 0)->utc(),
            'ends_at' => $day->setTime(14, 0)->utc(),
            'status' => 'confirmed',
            'transparency' => 'opaque',
            'location' => 'ジム',
            'synced_at' => now(),
        ]);

        YoyuPlace::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'name' => 'ジム',
            'travel_minutes' => 20,
        ]);

        YoyuTask::factory()->create([
            'user_id' => $user->id,
            'title' => '書類',
            'status' => 'planned',
            'estimate_minutes' => 60,
        ]);

        $area = LifeArea::factory()->create(['user_id' => $user->id, 'name' => '仕事']);
        $cell = MatrixCell::factory()->forRow(MatrixRowKey::Current)->create([
            'user_id' => $user->id,
            'life_area_id' => $area->id,
        ]);
        MatrixCellItem::factory()->create([
            'matrix_cell_id' => $cell->id,
            'title' => '応募書類を仕上げる',
            'is_completed' => false,
            'sort_order' => 1,
        ]);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $context = app(BriefingContextBuilder::class)->build($user, $day, $tz);

        $this->assertSame('2026-07-11', $context->briefingDate);
        $this->assertSame($tz, $context->timezone);
        $this->assertSame(CalendarConnectionStatus::Connected, $context->calendar->connectionStatus);
        $this->assertCount(1, $context->calendar->timedEvents());
        $this->assertSame(20, $context->calendar->timedEvents()[0]->travelMin);
        $this->assertSame('応募書類を仕上げる', $context->hand?->title);
        $this->assertSame(60, $context->margin->taskMinutes);
        $this->assertSame(95, $context->gaps->totalBusyMinutes);
        $this->assertLessThan(25, $queries);
    }

    public function test_disconnected_yields_empty_calendar_and_connect_warning(): void
    {
        $user = User::factory()->create();
        $context = app(BriefingContextBuilder::class)->build(
            $user,
            CarbonImmutable::parse('2026-07-11', 'Asia/Tokyo'),
            'Asia/Tokyo',
        );

        $this->assertSame(CalendarConnectionStatus::Disconnected, $context->calendar->connectionStatus);
        $this->assertSame([], $context->calendar->events);
        $this->assertSame('not_connected', $context->calendar->warningCode);
        $this->assertSame(0, $context->gaps->totalBusyMinutes);
    }

    public function test_other_users_tasks_and_places_are_excluded(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        YoyuTask::factory()->create([
            'user_id' => $other->id,
            'estimate_minutes' => 120,
            'status' => 'planned',
        ]);
        YoyuPlace::query()->withoutUserScope()->create([
            'user_id' => $other->id,
            'name' => 'ジム',
            'travel_minutes' => 99,
        ]);

        $context = app(BriefingContextBuilder::class)->build(
            $user,
            CarbonImmutable::parse('2026-07-11', 'Asia/Tokyo'),
            'Asia/Tokyo',
        );

        $this->assertSame(0, $context->margin->taskMinutes);
        $this->assertSame(0, $context->tasks->count());
    }
}
