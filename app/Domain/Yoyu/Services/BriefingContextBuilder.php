<?php

namespace App\Domain\Yoyu\Services;

use App\Domain\Connectors\Calendar\CalendarProviderResolver;
use App\Domain\Connectors\Calendar\CalendarSnapshot;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\RecallService;
use App\Domain\Yoyu\Data\BriefingContext;
use App\Domain\Yoyu\Data\BriefingMemoryRef;
use App\Domain\Yoyu\Models\YoyuTask;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * One-shot deterministic briefing inputs. No Google HTTP. No AI generation.
 */
final class BriefingContextBuilder
{
    public function __construct(
        private CalendarProviderResolver $calendars,
        private TravelTimeResolver $travel,
        private ClearDawnHandService $handService,
        private GapAnalyzer $gapAnalyzer,
        private MarginAnalyzer $marginAnalyzer,
        private RecallService $recall,
        private UserTimezoneResolver $timezones,
        private YoyuPreferenceService $preferences,
    ) {}

    public function build(
        User $user,
        CarbonImmutable|string $briefingDate,
        ?string $timezone = null,
    ): BriefingContext {
        $tz = $timezone ?? $this->timezones->for($user);
        $day = $briefingDate instanceof CarbonImmutable
            ? $briefingDate->timezone($tz)->startOfDay()
            : CarbonImmutable::parse($briefingDate, $tz)->startOfDay();

        $from = $day;
        $to = $day->addDay();

        $rawSnapshot = $this->calendars->for($user)->snapshotFor($user, $from, $to, $tz);
        $resolvedEvents = $this->travel->resolve($user, $rawSnapshot->events);

        $snapshot = new CalendarSnapshot(
            connectionStatus: $rawSnapshot->connectionStatus,
            events: $resolvedEvents,
            syncedAt: $rawSnapshot->syncedAt,
            isStale: $rawSnapshot->isStale,
            warningCode: $rawSnapshot->warningCode,
            accountEmail: $rawSnapshot->accountEmail,
        );

        $hand = $this->handService->forUser($user);
        $travelLead = $this->preferences->travelLeadFor($user);

        $tasks = YoyuTask::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'title', 'estimate_minutes', 'status', 'user_id', 'created_at']);

        $taskEstimateSum = (int) YoyuTask::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->sum('estimate_minutes');

        $memoryModels = $this->recall->memories(
            (int) $user->id,
            '朝ブリーフィング 今日の予定',
            5,
            countReference: false,
        );

        $memories = [];
        $recallLines = [];
        foreach ($memoryModels->values() as $index => $memory) {
            /** @var Memory $memory */
            $key = 'memory_'.($index + 1);
            $excerpt = $memory->summary ?: mb_substr((string) $memory->raw_content, 0, 200);
            $title = (string) ($memory->title ?: mb_substr((string) $memory->raw_content, 0, 40));
            $ref = new BriefingMemoryRef(
                key: $key,
                id: (string) $memory->id,
                title: $title,
                excerpt: $excerpt,
                url: route('kioku.memories.show', $memory),
            );
            $memories[] = $ref;
            $when = $memory->captured_at->diffForHumans();
            $type = $memory->memory_type ?? 'memory';
            $recallLines[] = "[{$when}/{$type}] {$excerpt}";
        }

        $gaps = $this->gapAnalyzer->analyze(
            $day->toDateString(),
            $tz,
            $resolvedEvents,
            $travelLead['prep_minutes'],
            $travelLead['buffer_minutes'],
        );
        $margin = $this->marginAnalyzer->analyze($gaps->totalBusyMinutes, $taskEstimateSum);

        return new BriefingContext(
            briefingDate: $day->toDateString(),
            timezone: $tz,
            calendar: $snapshot,
            hand: $hand,
            tasks: $tasks,
            memories: $memories,
            recallLines: $recallLines,
            gaps: $gaps,
            margin: $margin,
            travelLead: $travelLead,
        );
    }
}
