<?php

namespace Tests\Feature;

use App\Enums\ProgramVersionStatus;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
