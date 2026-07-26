<?php

namespace Database\Seeders;

use App\Enums\GoalStatus;
use App\Enums\MealType;
use App\Enums\RoutinePlanStatus;
use App\Enums\RoutineSessionStatus;
use App\Models\DailyCheckin;
use App\Models\Goal;
use App\Models\MealEntry;
use App\Models\RoutinePlan;
use App\Models\RoutineSession;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use App\Models\TeamProgram;
use App\Models\TeamProgramAssignment;
use App\Models\TeamProgramItem;
use App\Models\TeamUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

class TeamWorkspaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('TeamWorkspaceDemoSeeder is available only in local/testing environments.');
        }

        $coach = TeamUser::query()->updateOrCreate(
            ['google_subject' => 'local-demo-coach'],
            ['email' => 'coach@team.local', 'name' => '佐藤 コーチ', 'status' => 'active'],
        );

        $assistant = TeamUser::query()->updateOrCreate(
            ['google_subject' => 'local-demo-assistant'],
            ['email' => 'assistant@team.local', 'name' => '鈴木 アシスタント', 'status' => 'active'],
        );

        $team = Team::query()->updateOrCreate(
            ['slug' => 'clear-dawn-juniors'],
            [
                'name' => 'Clear Dawn ジュニアーズ',
                'organization_type' => 'club',
                'status' => 'active',
                'timezone' => 'Asia/Tokyo',
                'created_by_team_user_id' => $coach->id,
            ],
        );

        TeamMembership::query()->firstOrCreate(
            ['team_id' => $team->id, 'member_type' => 'team_user', 'member_id' => $coach->id, 'role' => 'head_coach'],
            ['status' => 'active', 'joined_at' => now()],
        );

        TeamMembership::query()->firstOrCreate(
            ['team_id' => $team->id, 'member_type' => 'team_user', 'member_id' => $assistant->id, 'role' => 'coach'],
            ['status' => 'active', 'joined_at' => now()->subDays(10)],
        );

        TeamInvitation::query()->updateOrCreate(
            ['team_id' => $team->id, 'email' => 'pending.coach@demo.local', 'invitee_type' => 'team_user'],
            [
                'role' => 'nutrition_staff',
                'token_hash' => hash('sha256', 'team-demo-pending-invitation'),
                'invited_by_team_user_id' => $coach->id,
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
            ],
        );

        $today = CarbonImmutable::now($team->timezone)->startOfDay();

        $athletes = collect([
            [
                'name' => '朝倉 陽太',
                'email' => 'yota.athlete@demo.local',
                'meal_days' => 7,
                'training_days' => [0, 1, 3, 5],
                'readiness' => 8,
                'fatigue' => 2,
                'goal' => ['name' => 'スプリント向上', 'status' => GoalStatus::Active, 'deadline_days' => 60],
            ],
            [
                'name' => '水野 蓮',
                'email' => 'ren.athlete@demo.local',
                'meal_days' => 4,
                'training_days' => [0, 2],
                'readiness' => 5,
                'fatigue' => 5,
                'goal' => ['name' => '持久力ベース作り', 'status' => GoalStatus::Active, 'deadline_days' => 90],
            ],
            [
                'name' => '高橋 蒼',
                'email' => 'ao.athlete@demo.local',
                'meal_days' => 2,
                'training_days' => [1],
                'readiness' => 3,
                'fatigue' => 7,
                'goal' => ['name' => 'ケガ予防の習慣化', 'status' => GoalStatus::Achieved, 'deadline_days' => -7],
            ],
        ])->map(function (array $demo) use ($team, $coach, $today): User {
            $athlete = User::query()->where('email', $demo['email'])->first()
                ?? User::factory()->create(['email' => $demo['email'], 'name' => $demo['name']]);

            TeamMembership::query()->firstOrCreate(
                ['team_id' => $team->id, 'member_type' => 'user', 'member_id' => $athlete->id, 'role' => 'athlete'],
                ['status' => 'active', 'joined_at' => now(), 'invited_by_team_user_id' => $coach->id],
            );

            foreach (range(0, $demo['meal_days'] - 1) as $daysAgo) {
                foreach ([MealType::Breakfast, MealType::Dinner] as $index => $mealType) {
                    MealEntry::query()->firstOrCreate(
                        [
                            'user_id' => $athlete->id,
                            'eaten_on' => $today->subDays($daysAgo)->toDateString(),
                            'meal_type' => $mealType,
                        ],
                        [
                            'name' => 'デモ食事'.($index + 1),
                            'quantity' => 1,
                            'kcal' => 0,
                            'protein_g' => 0,
                            'fat_g' => 0,
                            'carb_g' => 0,
                            'note' => '個人メモはチームへ共有しない',
                        ],
                    );
                }
            }

            foreach ($demo['training_days'] as $daysAgo) {
                $plan = RoutinePlan::query()->firstOrCreate(
                    [
                        'user_id' => $athlete->id,
                        'title' => 'チーム基礎練',
                        'scheduled_on' => $today->subDays($daysAgo)->toDateString(),
                    ],
                    ['status' => RoutinePlanStatus::Ready],
                );

                $startedAt = $today->subDays($daysAgo)->setTime(17, 0);
                RoutineSession::query()->updateOrCreate(
                    [
                        'user_id' => $athlete->id,
                        'routine_plan_id' => $plan->id,
                        'started_at' => $startedAt,
                    ],
                    [
                        'status' => RoutineSessionStatus::Completed,
                        'finished_at' => $startedAt->addMinutes(75),
                        'session_rpe' => 6.0,
                        'note' => '個人ノートは共有禁止',
                    ],
                );
            }

            foreach (range(0, 6) as $daysAgo) {
                if ($daysAgo > 0 && $daysAgo % 2 === 1 && $demo['meal_days'] < 5) {
                    continue;
                }

                $checkedOn = $today->subDays($daysAgo)->toDateString();
                $checkin = DailyCheckin::query()
                    ->where('user_id', $athlete->id)
                    ->whereDate('checked_on', $checkedOn)
                    ->first();

                $checkinAttributes = [
                    'sleep_quality' => max(1, $demo['readiness'] - 1),
                    'fatigue' => $demo['fatigue'],
                    'muscle_soreness' => $demo['fatigue'],
                    'stress' => 3,
                    'mood' => $demo['readiness'],
                    'readiness_self' => $demo['readiness'],
                    'note' => '症状メモは共有禁止',
                ];

                if ($checkin === null) {
                    DailyCheckin::query()->create([
                        'user_id' => $athlete->id,
                        'checked_on' => $checkedOn,
                        ...$checkinAttributes,
                    ]);
                } else {
                    $checkin->update($checkinAttributes);
                }
            }

            Goal::query()->updateOrCreate(
                [
                    'user_id' => $athlete->id,
                    'name' => $demo['goal']['name'],
                ],
                [
                    'why' => '個人的な動機はチーム画面へ出さない',
                    'priority' => 1,
                    'status' => $demo['goal']['status'],
                    'deadline' => $today->addDays($demo['goal']['deadline_days'])->toDateString(),
                    'sort_order' => 1,
                ],
            );

            return $athlete;
        });

        $program = TeamProgram::query()->updateOrCreate(
            ['team_id' => $team->id, 'title' => '夏期基礎体力プログラム'],
            [
                'starts_on' => $today->toDateString(),
                'ends_on' => $today->addWeeks(4)->toDateString(),
                'visibility_status' => 'published',
                'summary' => '走・体幹・回復の基礎メニューを週3で実施する読み取り専用デモです。',
            ],
        );

        collect(['ウォームアップ走', '体幹サーキット', 'クールダウン'])->each(
            function (string $title, int $index) use ($program): void {
                TeamProgramItem::query()->updateOrCreate(
                    ['team_program_id' => $program->id, 'title' => $title],
                    ['sort_order' => $index + 1],
                );
            },
        );

        $athletes->take(2)->each(function (User $athlete) use ($program): void {
            TeamProgramAssignment::query()->updateOrCreate(
                ['team_program_id' => $program->id, 'user_id' => $athlete->id],
                ['status' => 'assigned', 'assigned_at' => now()],
            );
        });

        TeamProgram::query()->updateOrCreate(
            ['team_id' => $team->id, 'title' => '秋期強化（下書き）'],
            [
                'starts_on' => $today->addWeeks(5)->toDateString(),
                'ends_on' => $today->addWeeks(9)->toDateString(),
                'visibility_status' => 'draft',
                'summary' => '公開前の下書きプログラムです。',
            ],
        );
    }
}
