<?php

namespace Tests\Feature;

use App\Models\PersonalProfileEntry;
use App\Models\Program;
use App\Models\User;
use App\Services\InstallElevenWeekProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgramTest extends TestCase
{
    use RefreshDatabase;

    private function installProgram(User $user): Program
    {
        return app(InstallElevenWeekProgramService::class)->handle($user);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $program = Program::factory()->create();

        $this->get(route('programs.index'))->assertRedirect(route('login'));
        $this->get(route('programs.show', $program))->assertRedirect(route('login'));
        $this->get(route('programs.roadmap', $program))->assertRedirect(route('login'));
    }

    public function test_index_lists_only_the_authenticated_users_programs(): void
    {
        $user = User::factory()->create();
        $this->installProgram($user);
        Program::factory()->create(['name' => '他人のプログラム']);

        $this->actingAs($user)
            ->get(route('programs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Programs/Index')
                ->has('programs', 1)
                ->where('programs.0.name', InstallElevenWeekProgramService::PROGRAM_NAME)
                ->where('programs.0.active_version.week_count', 11)
                ->where('programs.0.active_version.day_count', 7));
    }

    public function test_users_cannot_view_other_users_programs(): void
    {
        $user = User::factory()->create();
        $otherProgram = Program::factory()->create();

        $this->actingAs($user)->get(route('programs.show', $otherProgram))->assertForbidden();
        $this->actingAs($user)->get(route('programs.roadmap', $otherProgram))->assertForbidden();
    }

    public function test_show_displays_program_structure(): void
    {
        $user = User::factory()->create();
        $program = $this->installProgram($user);

        $this->actingAs($user)
            ->get(route('programs.show', $program))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Programs/Show')
                ->where('program.name', InstallElevenWeekProgramService::PROGRAM_NAME)
                ->has('program.active_version.phases', 5)
                ->has('program.active_version.day_templates', 7)
                ->has('program.active_version.constraints', 10)
                ->has('program.active_version.day_templates.1.choice_group.options', 4));
    }

    public function test_roadmap_displays_weeks_and_percent_based_prescriptions(): void
    {
        $user = User::factory()->create();
        $program = $this->installProgram($user);

        $this->actingAs($user)
            ->get(route('programs.roadmap', $program))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Programs/Roadmap')
                ->has('roadmap.phases', 5)
                ->has('roadmap.weeks', 11)
                ->has('roadmap.day_templates', 7)
                ->has('roadmap.weeks.0.prescriptions', 4)
                // 個人1RM 未投入の間は表示重量なし（percent のみ）
                ->where('roadmap.weeks.0.prescriptions.0.display_load', null));
    }

    public function test_roadmap_derives_display_load_from_personal_one_rep_max(): void
    {
        $user = User::factory()->create();
        $program = $this->installProgram($user);

        PersonalProfileEntry::factory()->create([
            'user_id' => $user->id,
            'key' => PersonalProfileEntry::KEY_ONE_RM_BENCH,
            'value_numeric' => 57,
            'unit' => 'kg',
            'effective_from' => '2026-07-01',
        ]);

        $response = $this->actingAs($user)->get(route('programs.roadmap', $program));
        $response->assertOk();

        $props = [];
        $response->assertInertia(function (Assert $page) use (&$props): void {
            $props = $page->toArray()['props'];
        });

        $week1Bench = collect($props['roadmap']['weeks'][0]['prescriptions'])
            ->first(fn (array $prescription) => $prescription['item_name'] === 'ベンチプレス');

        // 57kg × 0.7456 = 42.4992 → 1.25kg 丸めで 42.5
        $this->assertSame(42.5, $week1Bench['display_load']);
    }
}
