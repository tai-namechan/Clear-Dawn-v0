<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function exercisePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'ベンチプレス',
            'category' => 'strength',
            'tracking_type' => 'weight_reps',
        ], $overrides);
    }

    public function test_guests_cannot_access_exercise_management(): void
    {
        $exercise = Exercise::factory()->create();

        $this->get(route('exercises.index'))->assertRedirect(route('login'));
        $this->postJson(route('exercises.store'), $this->exercisePayload())->assertUnauthorized();
        $this->patchJson(route('exercises.update', $exercise), ['name' => '改ざん'])->assertUnauthorized();
        $this->deleteJson(route('exercises.destroy', $exercise))->assertUnauthorized();
    }

    public function test_index_shows_only_the_authenticated_users_active_exercises(): void
    {
        $user = User::factory()->create();
        Exercise::factory()->create(['user_id' => $user->id, 'name' => '自分の種目']);
        Exercise::factory()->inactive()->create(['user_id' => $user->id, 'name' => '自分の非表示種目']);
        Exercise::factory()->create(['name' => '他人の種目']);

        $this->actingAs($user)
            ->get(route('exercises.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Exercises/Index')
                ->has('exercises', 1)
                ->where('exercises.0.name', '自分の種目')
            );
    }

    public function test_user_can_create_an_exercise(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('exercises.store'), $this->exercisePayload([
            'name' => 'スクワット',
            'note' => 'フォーム確認',
        ]));

        $response->assertOk()
            ->assertJsonPath('exercise.name', 'スクワット')
            ->assertJsonPath('exercise.is_active', true);

        $this->assertDatabaseHas('exercises', [
            'user_id' => $user->id,
            'name' => 'スクワット',
            'note' => 'フォーム確認',
            'is_active' => true,
        ]);
    }

    public function test_user_can_update_their_own_exercise(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'name' => '旧名称']);

        $response = $this->actingAs($user)->patchJson(route('exercises.update', $exercise), [
            'name' => '新名称',
            'is_active' => false,
        ]);

        $response->assertOk()->assertJsonPath('exercise.name', '新名称');

        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'name' => '新名称',
            'is_active' => false,
        ]);
    }

    public function test_user_cannot_update_another_users_exercise(): void
    {
        $user = User::factory()->create();
        $otherExercise = Exercise::factory()->create(['name' => '他人の種目']);

        $this->actingAs($user)
            ->patchJson(route('exercises.update', $otherExercise), ['name' => '乗っ取り'])
            ->assertForbidden();

        $this->assertDatabaseHas('exercises', [
            'id' => $otherExercise->id,
            'name' => '他人の種目',
        ]);
    }

    public function test_user_can_soft_delete_their_own_exercise(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson(route('exercises.destroy', $exercise))
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertSoftDeleted('exercises', ['id' => $exercise->id]);
    }

    public function test_user_cannot_delete_another_users_exercise(): void
    {
        $user = User::factory()->create();
        $otherExercise = Exercise::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('exercises.destroy', $otherExercise))
            ->assertForbidden();

        $this->assertDatabaseHas('exercises', ['id' => $otherExercise->id, 'deleted_at' => null]);
    }

    public function test_deactivated_exercises_are_excluded_from_index(): void
    {
        $user = User::factory()->create();
        $active = Exercise::factory()->create(['user_id' => $user->id, 'name' => '有効な種目']);
        $inactive = Exercise::factory()->create(['user_id' => $user->id, 'name' => '無効化する種目']);

        $this->actingAs($user)->patchJson(route('exercises.update', $inactive), [
            'is_active' => false,
        ])->assertOk();

        $this->actingAs($user)
            ->get(route('exercises.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('exercises', 1)
                ->where('exercises.0.id', $active->id)
            );
    }

    public function test_index_counts_videos_scoped_to_each_exercise(): void
    {
        $user = User::factory()->create();
        $bench = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'ベンチ']);
        $squat = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'スクワット']);

        Video::factory()->ready()->count(2)->create([
            'user_id' => $user->id,
            'exercise_id' => $bench->id,
        ]);
        Video::factory()->ready()->create([
            'user_id' => $user->id,
            'exercise_id' => $squat->id,
        ]);
        // 同一ユーザーだが exercise_id 未設定の動画はカウントに含めない
        Video::factory()->ready()->create([
            'user_id' => $user->id,
            'exercise_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('exercises.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('exercises', fn ($exercises) => collect($exercises)->contains(
                    fn (array $exercise): bool => $exercise['id'] === $bench->id && $exercise['videos_count'] === 2,
                ))
                ->where('exercises', fn ($exercises) => collect($exercises)->contains(
                    fn (array $exercise): bool => $exercise['id'] === $squat->id && $exercise['videos_count'] === 1,
                ))
            );
    }
}
