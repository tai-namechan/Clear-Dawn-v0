<?php

namespace Database\Seeders;

use App\Enums\MealType;
use App\Models\DailyCheckin;
use App\Models\MealEntry;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\TeamUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class TeamWorkspaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException('TeamWorkspaceDemoSeeder is available only in the local environment.');
        }

        $coach = TeamUser::query()->updateOrCreate(
            ['google_subject' => 'local-demo-coach'],
            ['email' => 'coach@team.local', 'name' => '佐藤 コーチ', 'status' => 'active'],
        );

        $team = Team::query()->updateOrCreate(
            ['slug' => 'clear-dawn-juniors'],
            ['name' => 'Clear Dawn ジュニアーズ', 'organization_type' => 'club', 'status' => 'active', 'timezone' => 'Asia/Tokyo', 'created_by_team_user_id' => $coach->id],
        );

        TeamMembership::query()->firstOrCreate(
            ['team_id' => $team->id, 'member_type' => 'team_user', 'member_id' => $coach->id, 'role' => 'head_coach'],
            ['status' => 'active', 'joined_at' => now()],
        );

        collect([
            ['name' => '朝倉 陽太', 'email' => 'yota.athlete@demo.local', 'meal_days' => 7],
            ['name' => '水野 蓮', 'email' => 'ren.athlete@demo.local', 'meal_days' => 4],
            ['name' => '高橋 蒼', 'email' => 'ao.athlete@demo.local', 'meal_days' => 2],
        ])->each(function (array $demo) use ($team, $coach): void {
            $athlete = User::query()->where('email', $demo['email'])->first()
                ?? User::factory()->create(['email' => $demo['email'], 'name' => $demo['name']]);
            TeamMembership::query()->firstOrCreate(
                ['team_id' => $team->id, 'member_type' => 'user', 'member_id' => $athlete->id, 'role' => 'athlete'],
                ['status' => 'active', 'joined_at' => now(), 'invited_by_team_user_id' => $coach->id],
            );

            foreach (range(0, $demo['meal_days'] - 1) as $daysAgo) {
                MealEntry::query()->firstOrCreate(
                    ['user_id' => $athlete->id, 'eaten_on' => today()->subDays($daysAgo), 'meal_type' => MealType::Breakfast],
                    ['name' => 'デモ朝食', 'quantity' => 1, 'kcal' => 0, 'protein_g' => 0, 'fat_g' => 0, 'carb_g' => 0],
                );
            }

            DailyCheckin::query()->updateOrCreate(
                ['user_id' => $athlete->id, 'checked_on' => today()],
                ['sleep_quality' => 7, 'fatigue' => 3, 'muscle_soreness' => 2, 'stress' => 2, 'mood' => 8, 'readiness_self' => 7],
            );
        });
    }
}
