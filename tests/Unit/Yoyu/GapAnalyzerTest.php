<?php

namespace Tests\Unit\Yoyu;

use App\Domain\Connectors\Calendar\CalendarEventData;
use App\Domain\Yoyu\Services\GapAnalyzer;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class GapAnalyzerTest extends TestCase
{
    private GapAnalyzer $analyzer;

    private string $tz = 'Asia/Tokyo';

    private string $date = '2026-07-11';

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new GapAnalyzer;
    }

    private function timed(
        string $id,
        string $startLocal,
        string $endLocal,
        array $overrides = [],
    ): CalendarEventData {
        return new CalendarEventData(
            externalId: $id,
            title: $id,
            allDay: $overrides['allDay'] ?? false,
            startsAt: CarbonImmutable::parse($this->date.' '.$startLocal, $this->tz)->utc(),
            endsAt: CarbonImmutable::parse($this->date.' '.$endLocal, $this->tz)->utc(),
            startsOn: null,
            endsOn: null,
            timezone: $this->tz,
            status: $overrides['status'] ?? 'confirmed',
            transparency: $overrides['transparency'] ?? 'opaque',
            location: $overrides['location'] ?? null,
            travelMin: $overrides['travelMin'] ?? null,
            color: null,
            prepMinutesOverride: $overrides['prepMinutesOverride'] ?? null,
            bufferMinutesOverride: $overrides['bufferMinutesOverride'] ?? null,
        );
    }

    public function test_zero_events_yields_full_day_gap(): void
    {
        $result = $this->analyzer->analyze($this->date, $this->tz, []);

        $this->assertSame(0, $result->totalBusyMinutes);
        $this->assertCount(1, $result->allGaps);
        $this->assertSame(960, $result->allGaps[0]->minutes);
        $this->assertSame('gap_1', $result->suggestibleGaps[0]->key);
    }

    public function test_single_event(): void
    {
        $result = $this->analyzer->analyze($this->date, $this->tz, [
            $this->timed('e1', '10:00', '11:00'),
        ]);

        $this->assertSame(60, $result->totalBusyMinutes);
        $this->assertCount(2, $result->suggestibleGaps);
    }

    public function test_overlapping_events_merge_busy_minutes(): void
    {
        $result = $this->analyzer->analyze($this->date, $this->tz, [
            $this->timed('e1', '10:00', '12:00'),
            $this->timed('e2', '11:00', '13:00'),
        ]);

        $this->assertSame(180, $result->totalBusyMinutes);
    }

    public function test_fully_contained_event_does_not_double_count(): void
    {
        $result = $this->analyzer->analyze($this->date, $this->tz, [
            $this->timed('outer', '09:00', '15:00'),
            $this->timed('inner', '10:00', '11:00'),
        ]);

        $this->assertSame(360, $result->totalBusyMinutes);
    }

    public function test_abutting_events_merge(): void
    {
        $result = $this->analyzer->analyze($this->date, $this->tz, [
            $this->timed('e1', '10:00', '11:00'),
            $this->timed('e2', '11:00', '12:00'),
        ]);

        $this->assertSame(120, $result->totalBusyMinutes);
        $this->assertCount(1, $result->mergedBusyIntervals);
    }

    public function test_event_clamped_to_working_hours(): void
    {
        $result = $this->analyzer->analyze($this->date, $this->tz, [
            $this->timed('early', '05:00', '08:00'),
        ]);

        $this->assertSame(60, $result->totalBusyMinutes);
    }

    public function test_overnight_event_clamped_to_day_end(): void
    {
        $start = CarbonImmutable::parse($this->date.' 22:00', $this->tz)->utc();
        $end = CarbonImmutable::parse('2026-07-12 01:00', $this->tz)->utc();
        $event = new CalendarEventData(
            externalId: 'night',
            title: 'night',
            allDay: false,
            startsAt: $start,
            endsAt: $end,
            startsOn: null,
            endsOn: null,
            timezone: $this->tz,
        );

        $result = $this->analyzer->analyze($this->date, $this->tz, [$event]);

        $this->assertSame(60, $result->totalBusyMinutes);
    }

    public function test_all_day_cancelled_transparent_and_invalid_are_ignored(): void
    {
        $allDay = new CalendarEventData(
            externalId: 'ad',
            title: 'holiday',
            allDay: true,
            startsAt: null,
            endsAt: null,
            startsOn: $this->date,
            endsOn: '2026-07-12',
            timezone: $this->tz,
        );

        $result = $this->analyzer->analyze($this->date, $this->tz, [
            $allDay,
            $this->timed('c', '10:00', '11:00', ['status' => 'cancelled']),
            $this->timed('t', '12:00', '13:00', ['transparency' => 'transparent']),
            $this->timed('bad', '15:00', '14:00'),
        ]);

        $this->assertSame(0, $result->totalBusyMinutes);
        $this->assertSame(1, $result->ignoredEventCounts['all_day']);
        $this->assertSame(1, $result->ignoredEventCounts['cancelled']);
        $this->assertSame(1, $result->ignoredEventCounts['transparent']);
        $this->assertSame(1, $result->ignoredEventCounts['invalid']);
    }

    public function test_thirty_minute_boundary_is_suggestible(): void
    {
        $result = $this->analyzer->analyze($this->date, $this->tz, [
            $this->timed('e1', '07:00', '10:00'),
            $this->timed('e2', '10:30', '23:00'),
        ]);

        $keys = array_map(fn ($g) => $g->minutes, $result->suggestibleGaps);
        $this->assertContains(30, $keys);

        $result29 = $this->analyzer->analyze($this->date, $this->tz, [
            $this->timed('e1', '07:00', '10:00'),
            $this->timed('e2', '10:29', '23:00'),
        ]);
        $this->assertTrue(collect($result29->suggestibleGaps)->every(fn ($g) => $g->minutes !== 29));
        $this->assertTrue(collect($result29->allGaps)->contains(fn ($g) => $g->minutes === 29));
    }

    public function test_suggestible_capped_at_five_keyed_in_start_order(): void
    {
        // Create many small busy blocks leaving >5 gaps of 60min+
        $events = [];
        for ($h = 8; $h <= 20; $h += 2) {
            $events[] = $this->timed("b{$h}", sprintf('%02d:00', $h), sprintf('%02d:30', $h));
        }

        $result = $this->analyzer->analyze($this->date, $this->tz, $events);

        $this->assertLessThanOrEqual(5, count($result->suggestibleGaps));
        $this->assertSame('gap_1', $result->suggestibleGaps[0]->key);
        $starts = array_map(fn ($g) => $g->start->getTimestamp(), $result->suggestibleGaps);
        $sorted = $starts;
        sort($sorted);
        $this->assertSame($sorted, $starts);
    }

    public function test_travel_lead_extends_busy_and_null_does_not(): void
    {
        $withTravel = $this->analyzer->analyze($this->date, $this->tz, [
            $this->timed('e1', '10:00', '11:00', ['travelMin' => 20]),
        ]);
        // 20 + 10 prep + 5 buffer = 35 lead → busy 10:00-11:00 becomes 09:25-11:00 = 95
        $this->assertSame(95, $withTravel->totalBusyMinutes);

        $without = $this->analyzer->analyze($this->date, $this->tz, [
            $this->timed('e1', '10:00', '11:00', ['travelMin' => null]),
        ]);
        $this->assertSame(60, $without->totalBusyMinutes);
    }

    public function test_per_event_prep_buffer_override_changes_only_that_event(): void
    {
        $result = $this->analyzer->analyze(
            $this->date,
            $this->tz,
            [
                $this->timed('custom', '10:00', '11:00', [
                    'travelMin' => 20,
                    'prepMinutesOverride' => 30,
                    'bufferMinutesOverride' => 10,
                ]),
                $this->timed('default', '14:00', '15:00', [
                    'travelMin' => 20,
                ]),
            ],
            prepMinutes: 10,
            bufferMinutes: 5,
        );

        // custom: 20+30+10=60 lead → 09:00-11:00 = 120
        // default: 20+10+5=35 lead → 13:25-15:00 = 95
        $this->assertSame(215, $result->totalBusyMinutes);
    }

    public function test_travel_lead_clamped_to_work_start_and_merges_overlap(): void
    {
        $result = $this->analyzer->analyze($this->date, $this->tz, [
            $this->timed('early', '07:10', '08:00', ['travelMin' => 30]), // lead 45 → before 07:00
            $this->timed('next', '08:00', '09:00', ['travelMin' => 20]), // lead → 07:25, overlaps first
        ]);

        $this->assertCount(1, $result->mergedBusyIntervals);
        $this->assertSame(120, $result->totalBusyMinutes); // 07:00-09:00
    }

    public function test_america_new_york_timezone_working_window(): void
    {
        $tz = 'America/New_York';
        $date = '2026-07-11';
        $event = new CalendarEventData(
            externalId: 'e1',
            title: 'e1',
            allDay: false,
            startsAt: CarbonImmutable::parse($date.' 10:00', $tz)->utc(),
            endsAt: CarbonImmutable::parse($date.' 11:00', $tz)->utc(),
            startsOn: null,
            endsOn: null,
            timezone: $tz,
        );

        $result = $this->analyzer->analyze($date, $tz, [$event]);
        $this->assertSame(60, $result->totalBusyMinutes);
    }
}
