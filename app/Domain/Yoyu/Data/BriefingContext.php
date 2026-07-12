<?php

namespace App\Domain\Yoyu\Data;

use App\Domain\Connectors\Calendar\CalendarSnapshot;
use App\Domain\Yoyu\Models\YoyuTask;
use Illuminate\Support\Collection;

final readonly class BriefingContext
{
    /**
     * @param  Collection<int, YoyuTask>  $tasks
     * @param  list<string>  $recallLines
     * @param  array{prep_minutes: int, buffer_minutes: int}  $travelLead
     */
    public function __construct(
        public string $briefingDate,
        public string $timezone,
        public CalendarSnapshot $calendar,
        public ?ClearDawnHand $hand,
        public Collection $tasks,
        public array $recallLines,
        public GapAnalysis $gaps,
        public MarginAnalysis $margin,
        public array $travelLead,
    ) {}

    /**
     * Deterministic analysis payload (no AI text). Safe to persist later as structured_data.analysis.
     *
     * @return array<string, mixed>
     */
    public function analysisArray(): array
    {
        return [
            'briefing_date' => $this->briefingDate,
            'timezone' => $this->timezone,
            'calendar' => [
                'connection_status' => $this->calendar->connectionStatus->value,
                'synced_at' => $this->calendar->syncedAt?->toIso8601String(),
                'is_stale' => $this->calendar->isStale,
                'warning_code' => $this->calendar->warningCode,
            ],
            'hand' => $this->hand?->toClientArray(),
            'margin' => $this->margin->toArray(),
            'gaps' => $this->gaps->toArray($this->timezone),
            'travel_lead' => $this->travelLead,
            'task_count' => $this->tasks->count(),
            'recall_count' => count($this->recallLines),
        ];
    }
}
