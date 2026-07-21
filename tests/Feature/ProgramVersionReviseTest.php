<?php

namespace Tests\Feature;

use App\Enums\ProgramVersionStatus;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProgramVersionReviseTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_revise_program_version_copy_on_write(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();

        $program = Program::query()->where('user_id', $user->id)->firstOrFail();
        $oldVersion = $program->activeVersion;

        $this->actingAs($user)
            ->postJson(route('programs.versions.store', $program), [
                'change_summary' => 'W3 ベンチ処方を微調整',
                'change_reason' => '肘負荷を下げるため',
            ])
            ->assertCreated()
            ->assertJsonPath('version.version_number', 2)
            ->assertJsonPath('version.status', ProgramVersionStatus::Active->value);

        $this->assertSame(ProgramVersionStatus::Superseded, $oldVersion->fresh()->status);
        $this->assertSame(2, $program->versions()->count());

        $newVersion = $program->activeVersion()->firstOrFail();
        $this->assertSame(7, $newVersion->dayTemplates()->count());
        $this->assertSame(11, $newVersion->weeks()->count());
    }

    public function test_revise_rejects_ends_on_before_effective_starts_on(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();

        $program = Program::query()->where('user_id', $user->id)->firstOrFail();
        $startsOn = $program->activeVersion->starts_on->toDateString();

        // starts_on を省略し、実効開始日（現行版の開始日）より前の ends_on を指定
        $this->actingAs($user)
            ->postJson(route('programs.versions.store', $program), [
                'change_summary' => '期間短縮',
                'change_reason' => 'テスト',
                'ends_on' => Carbon::parse($startsOn)->subDay()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_on']);

        $this->assertSame(1, $program->versions()->count());
    }

    public function test_revise_with_shifted_start_recalculates_week_dates(): void
    {
        $user = User::factory()->create();
        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();

        $program = Program::query()->where('user_id', $user->id)->firstOrFail();
        $oldVersion = $program->activeVersion;
        $oldWeek1Start = Carbon::parse($oldVersion->weeks->firstWhere('week_number', 1)->starts_on);

        $newStart = $oldWeek1Start->copy()->addWeeks(2);
        $newEnd = $newStart->copy()->addWeeks(11)->subDay();

        $this->actingAs($user)
            ->postJson(route('programs.versions.store', $program), [
                'change_summary' => '開始日シフトテスト',
                'change_reason' => '2週遅延のため',
                'starts_on' => $newStart->toDateString(),
                'ends_on' => $newEnd->toDateString(),
            ])
            ->assertCreated();

        $newVersion = $program->activeVersion()->with('weeks')->firstOrFail();

        $this->assertSame($newStart->toDateString(), $newVersion->starts_on->toDateString());

        $newWeek1 = $newVersion->weeks->firstWhere('week_number', 1);
        $this->assertSame($newStart->toDateString(), $newWeek1->starts_on->toDateString());

        $newWeek2 = $newVersion->weeks->firstWhere('week_number', 2);
        $this->assertSame($newStart->copy()->addWeek()->toDateString(), $newWeek2->starts_on->toDateString());

        // weekFor should resolve correctly for the new date range
        $this->assertNotNull($newVersion->weekFor($newStart));
        $this->assertNull($newVersion->weekFor($oldWeek1Start));
    }

    public function test_other_user_cannot_revise_program(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->artisan('cleardawn:install-program', ['userId' => $owner->id])->assertSuccessful();
        $program = Program::query()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($other)
            ->postJson(route('programs.versions.store', $program), [
                'change_summary' => 'x',
                'change_reason' => 'y',
            ])
            ->assertForbidden();
    }
}
