<?php

namespace App\Domain\Yoyu\Services;

use App\Domain\Connectors\Calendar\CalendarEventData;
use App\Domain\Yoyu\Data\GapAnalysis;
use App\Domain\Yoyu\Data\GapSlot;
use App\Domain\Yoyu\Support\YoyuTravelConstants;
use Carbon\CarbonImmutable;

/**
 * Pure gap / busy-interval analysis. No DB, no "now", no AI.
 */
final class GapAnalyzer
{
    /**
     * @param  list<CalendarEventData>  $events  travel_min already resolved (null = no lead)
     */
    public function analyze(
        string $localDate,
        string $timezone,
        array $events,
        int $prepMinutes = YoyuTravelConstants::PREP_MINUTES,
        int $bufferMinutes = YoyuTravelConstants::BUFFER_MINUTES,
    ): GapAnalysis {
        $prepMinutes = max(0, $prepMinutes);
        $bufferMinutes = max(0, $bufferMinutes);

        $dayStart = CarbonImmutable::parse($localDate.' 00:00:00', $timezone);
        $workStart = $dayStart->setTime(YoyuTravelConstants::WORKING_START_HOUR, 0);
        $workEnd = $dayStart->setTime(YoyuTravelConstants::WORKING_END_HOUR, 0);

        $ignored = [
            'cancelled' => 0,
            'transparent' => 0,
            'all_day' => 0,
            'invalid' => 0,
        ];

        /** @var list<array{0: CarbonImmutable, 1: CarbonImmutable}> $busy */
        $busy = [];

        foreach ($events as $event) {
            if ($event->isCancelled()) {
                $ignored['cancelled']++;

                continue;
            }

            if ($event->isTransparent()) {
                $ignored['transparent']++;

                continue;
            }

            if ($event->allDay) {
                $ignored['all_day']++;

                continue;
            }

            if ($event->startsAt === null || $event->endsAt === null) {
                $ignored['invalid']++;

                continue;
            }

            $start = $event->startsAt->timezone($timezone);
            $end = $event->endsAt->timezone($timezone);

            if ($end->lessThanOrEqualTo($start)) {
                $ignored['invalid']++;

                continue;
            }

            if ($event->travelMin !== null) {
                $prep = $event->effectivePrepMinutes($prepMinutes);
                $buffer = $event->effectiveBufferMinutes($bufferMinutes);
                $lead = $event->travelMin + $prep + $buffer;
                $start = $start->subMinutes($lead);
            }

            if ($end->lessThanOrEqualTo($workStart) || $start->greaterThanOrEqualTo($workEnd)) {
                continue;
            }

            $clampedStart = $start->lessThan($workStart) ? $workStart : $start;
            $clampedEnd = $end->greaterThan($workEnd) ? $workEnd : $end;

            if ($clampedEnd->lessThanOrEqualTo($clampedStart)) {
                continue;
            }

            $busy[] = [$clampedStart, $clampedEnd];
        }

        usort(
            $busy,
            fn (array $a, array $b): int => $a[0]->getTimestamp() <=> $b[0]->getTimestamp(),
        );

        $merged = $this->mergeIntervals($busy);
        $totalBusy = 0;
        foreach ($merged as [$start, $end]) {
            $totalBusy += $this->minutesBetween($start, $end);
        }

        $allGaps = $this->computeGaps($workStart, $workEnd, $merged);
        $suggestible = $this->selectSuggestibleGaps($allGaps);

        return new GapAnalysis(
            mergedBusyIntervals: array_map(
                fn (array $interval): array => ['start' => $interval[0], 'end' => $interval[1]],
                $merged,
            ),
            totalBusyMinutes: $totalBusy,
            allGaps: $allGaps,
            suggestibleGaps: $suggestible,
            ignoredEventCounts: $ignored,
        );
    }

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $busy
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function mergeIntervals(array $busy): array
    {
        if ($busy === []) {
            return [];
        }

        $merged = [];
        foreach ($busy as [$start, $end]) {
            if ($merged === []) {
                $merged[] = [$start, $end];

                continue;
            }

            $lastIndex = count($merged) - 1;
            [$lastStart, $lastEnd] = $merged[$lastIndex];

            // Overlap or abutting (end == next start) → merge.
            if ($start->lessThanOrEqualTo($lastEnd)) {
                $merged[$lastIndex] = [
                    $lastStart,
                    $end->greaterThan($lastEnd) ? $end : $lastEnd,
                ];
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $merged
     * @return list<GapSlot>
     */
    private function computeGaps(
        CarbonImmutable $workStart,
        CarbonImmutable $workEnd,
        array $merged,
    ): array {
        $gaps = [];
        $cursor = $workStart;
        $index = 0;

        foreach ($merged as [$start, $end]) {
            if ($start->greaterThan($cursor)) {
                $minutes = $this->minutesBetween($cursor, $start);
                $gaps[] = new GapSlot(
                    key: 'gap_tmp_'.$index,
                    start: $cursor,
                    end: $start,
                    minutes: $minutes,
                );
                $index++;
            }
            $cursor = $end;
        }

        if ($workEnd->greaterThan($cursor)) {
            $gaps[] = new GapSlot(
                key: 'gap_tmp_'.$index,
                start: $cursor,
                end: $workEnd,
                minutes: $this->minutesBetween($cursor, $workEnd),
            );
        }

        return $gaps;
    }

    /**
     * Longest first (max 5), then re-key in start order as gap_1..gap_n.
     *
     * @param  list<GapSlot>  $allGaps
     * @return list<GapSlot>
     */
    private function selectSuggestibleGaps(array $allGaps): array
    {
        $candidates = array_values(array_filter(
            $allGaps,
            fn (GapSlot $gap): bool => $gap->minutes >= YoyuTravelConstants::MIN_GAP_MINUTES,
        ));

        usort(
            $candidates,
            function (GapSlot $a, GapSlot $b): int {
                if ($a->minutes !== $b->minutes) {
                    return $b->minutes <=> $a->minutes;
                }

                return $a->start->getTimestamp() <=> $b->start->getTimestamp();
            },
        );

        $selected = array_slice($candidates, 0, YoyuTravelConstants::MAX_SUGGESTIBLE_GAPS);

        usort(
            $selected,
            fn (GapSlot $a, GapSlot $b): int => $a->start->getTimestamp() <=> $b->start->getTimestamp(),
        );

        $keyed = [];
        foreach ($selected as $i => $gap) {
            $keyed[] = new GapSlot(
                key: 'gap_'.($i + 1),
                start: $gap->start,
                end: $gap->end,
                minutes: $gap->minutes,
            );
        }

        return $keyed;
    }

    private function minutesBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) max(0, intdiv($end->getTimestamp() - $start->getTimestamp(), 60));
    }
}
