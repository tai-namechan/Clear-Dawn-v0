<?php

namespace App\Queries;

use App\Models\Team;
use App\Models\TeamProgram;
use Illuminate\Support\Collection;

class TeamProgramsQuery
{
    /** @return array<string, mixed> */
    public function handle(Team $team): array
    {
        $athleteCount = $team->memberships()
            ->where('member_type', 'user')
            ->where('role', 'athlete')
            ->where('status', 'active')
            ->count();

        /** @var Collection<int, TeamProgram> $programs */
        $programs = TeamProgram::query()
            ->where('team_id', $team->id)
            ->with([
                'items:id,team_program_id,title,sort_order',
                'assignments:id,team_program_id,user_id,status',
            ])
            ->orderByDesc('starts_on')
            ->orderBy('title')
            ->get();

        return [
            'athlete_count' => $athleteCount,
            'programs' => $programs->map(function (TeamProgram $program) use ($athleteCount): array {
                $assigned = $program->assignments->where('status', 'assigned')->count();

                return [
                    'id' => $program->id,
                    'title' => $program->title,
                    'starts_on' => $program->starts_on?->toDateString(),
                    'ends_on' => $program->ends_on?->toDateString(),
                    'visibility_status' => $program->visibility_status,
                    'visibility_label' => match ($program->visibility_status) {
                        'published' => '公開中',
                        'archived' => 'アーカイブ',
                        default => '下書き',
                    },
                    'summary' => $program->summary,
                    'item_titles' => $program->items->take(5)->pluck('title')->values()->all(),
                    'item_count' => $program->items->count(),
                    'assigned_count' => $assigned,
                    'athlete_count' => $athleteCount,
                    'assignment_label' => $athleteCount === 0
                        ? '在籍選手なし'
                        : "{$assigned} / {$athleteCount} 名に割当",
                ];
            })->values()->all(),
        ];
    }
}
