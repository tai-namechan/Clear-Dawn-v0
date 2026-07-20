<?php

namespace Tests\Feature;

use App\Models\Metric;
use App\Models\PersonalProfileEntry;
use App\Models\Program;
use App\Models\ProgramStepItem;
use App\Models\ProgramWeekItemPrescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProgramInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_program_command_creates_the_eleven_week_program(): void
    {
        $user = User::factory()->create();

        $this->artisan('cleardawn:install-program', ['userId' => $user->id])
            ->assertSuccessful();

        $program = Program::query()->where('user_id', $user->id)->firstOrFail();
        $version = $program->versions()->firstOrFail();

        $this->assertSame(5, $version->phases()->count());
        $this->assertSame(11, $version->weeks()->count());
        $this->assertSame(7, $version->dayTemplates()->count());
        $this->assertSame(10, $version->constraints()->count());

        // メインリフト3種 + ベンチセカンダリ × 11週 = 44 の週次処方
        $this->assertSame(44, ProgramWeekItemPrescription::query()->count());
        $this->assertSame(4, ProgramWeekItemPrescription::query()->where('is_test', true)->count());
    }

    public function test_install_program_command_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();
        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();

        $this->assertSame(1, Program::query()->where('user_id', $user->id)->count());
        $this->assertSame(44, ProgramWeekItemPrescription::query()->count());
    }

    public function test_install_program_command_fails_for_unknown_user(): void
    {
        $this->artisan('cleardawn:install-program', ['userId' => 999])->assertFailed();
    }

    public function test_install_program_command_defaults_to_the_sole_user_when_user_id_is_omitted(): void
    {
        $user = User::factory()->create();

        $this->artisan('cleardawn:install-program')->assertSuccessful();

        $this->assertSame(1, Program::query()->where('user_id', $user->id)->count());
    }

    public function test_install_program_command_fails_without_user_id_when_no_users_exist(): void
    {
        $this->artisan('cleardawn:install-program')->assertFailed();
    }

    public function test_install_program_command_fails_without_user_id_when_multiple_users_exist(): void
    {
        User::factory()->count(2)->create();

        $this->artisan('cleardawn:install-program')->assertFailed();

        $this->assertSame(0, Program::query()->count());
    }

    public function test_main_lift_prescriptions_are_stored_as_percent_of_reference(): void
    {
        $user = User::factory()->create();

        $this->artisan('cleardawn:install-program', ['userId' => $user->id])->assertSuccessful();

        $version = Program::query()->where('user_id', $user->id)->firstOrFail()->versions()->firstOrFail();
        $week3 = $version->weeks()->where('week_number', 3)->firstOrFail();

        $benchMain = ProgramStepItem::query()
            ->where('reference_lift', PersonalProfileEntry::KEY_ONE_RM_BENCH)
            ->whereHas('dayStep', fn ($query) => $query->where('name', 'メインセット'))
            ->firstOrFail();

        $prescription = $week3->itemPrescriptions()
            ->where('program_step_item_id', $benchMain->id)
            ->firstOrFail();

        $this->assertSame('0.8333', $prescription->percent_of_reference);
        $this->assertSame(3, $prescription->sets);
        $this->assertSame(5, $prescription->reps);
        $this->assertNull($benchMain->fixed_load, '個人実測値をリポジトリ・seed に含めない');
    }

    public function test_import_personal_command_upserts_entries_by_key_and_effective_date(): void
    {
        $user = User::factory()->create();
        $path = base_path('personal/profile-test.json');
        @mkdir(dirname($path));

        file_put_contents($path, json_encode([
            ['key' => 'one_rm_bench', 'value_numeric' => 57, 'unit' => 'kg', 'effective_from' => '2026-07-16'],
            ['key' => 'injury_history', 'value_text' => '腰椎ヘルニア', 'effective_from' => '2026-07-16'],
        ]));

        try {
            $this->artisan('cleardawn:import-personal', [
                'userId' => $user->id,
                '--path' => 'personal/profile-test.json',
            ])->assertSuccessful();

            $this->assertSame(2, PersonalProfileEntry::query()->where('user_id', $user->id)->count());

            // 同一 (key, effective_from) は上書き
            file_put_contents($path, json_encode([
                ['key' => 'one_rm_bench', 'value_numeric' => 60, 'unit' => 'kg', 'effective_from' => '2026-07-16'],
            ]));

            $this->artisan('cleardawn:import-personal', [
                'userId' => $user->id,
                '--path' => 'personal/profile-test.json',
            ])->assertSuccessful();

            $entry = PersonalProfileEntry::currentFor($user->fresh(), 'one_rm_bench');
            $this->assertNotNull($entry);
            $this->assertSame('60.000', $entry->value_numeric);
            $this->assertSame(2, PersonalProfileEntry::query()->where('user_id', $user->id)->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_import_personal_command_keeps_history_for_new_effective_dates(): void
    {
        $user = User::factory()->create();

        PersonalProfileEntry::factory()->create([
            'user_id' => $user->id,
            'key' => 'one_rm_bench',
            'value_numeric' => 57,
            'effective_from' => '2026-07-01',
        ]);

        $path = base_path('personal/profile-test.json');
        @mkdir(dirname($path));
        file_put_contents($path, json_encode([
            ['key' => 'one_rm_bench', 'value_numeric' => 60, 'effective_from' => '2026-08-01'],
        ]));

        try {
            $this->artisan('cleardawn:import-personal', [
                'userId' => $user->id,
                '--path' => 'personal/profile-test.json',
            ])->assertSuccessful();

            $this->assertSame(2, PersonalProfileEntry::query()->where('user_id', $user->id)->count());

            $current = PersonalProfileEntry::currentFor($user, 'one_rm_bench', Carbon::parse('2026-08-02'));
            $this->assertSame('60.000', $current?->value_numeric);

            $past = PersonalProfileEntry::currentFor($user, 'one_rm_bench', Carbon::parse('2026-07-10'));
            $this->assertSame('57.000', $past?->value_numeric);
        } finally {
            @unlink($path);
        }
    }

    public function test_import_personal_command_rejects_entries_without_value(): void
    {
        $user = User::factory()->create();
        $path = base_path('personal/profile-test.json');
        @mkdir(dirname($path));
        file_put_contents($path, json_encode([
            ['key' => 'one_rm_bench', 'effective_from' => '2026-07-16'],
        ]));

        try {
            $this->artisan('cleardawn:import-personal', [
                'userId' => $user->id,
                '--path' => 'personal/profile-test.json',
            ])->assertFailed();

            $this->assertSame(0, PersonalProfileEntry::query()->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_user_defined_metric_can_reuse_a_global_metric_key(): void
    {
        $user = User::factory()->create();

        Metric::query()->create([
            'key' => 'weight',
            'label' => '体重',
            'unit' => 'kg',
            'value_type' => 'decimal',
            'sort_order' => 1,
        ]);

        $userMetric = Metric::query()->create([
            'user_id' => $user->id,
            'key' => 'weight',
            'label' => '体重（朝イチ）',
            'unit' => 'kg',
            'value_type' => 'decimal',
            'sort_order' => 100,
            'is_advanced' => true,
        ]);

        $this->assertDatabaseCount('metrics', 2);
        $this->assertTrue($userMetric->fresh()->is_advanced);
    }
}
