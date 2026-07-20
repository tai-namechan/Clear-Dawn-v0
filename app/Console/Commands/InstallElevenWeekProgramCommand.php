<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InstallElevenWeekProgramService;
use Illuminate\Console\Command;

class InstallElevenWeekProgramCommand extends Command
{
    protected $signature = 'cleardawn:install-program {userId? : users.id（省略時はユーザーが1人だけならそのユーザー）}';

    protected $description = '11週 統合プログラム（筋力・投球・栄養）を登録する（冪等）';

    public function handle(InstallElevenWeekProgramService $service): int
    {
        $user = $this->resolveUser();

        if ($user === null) {
            return self::FAILURE;
        }

        $program = $service->handle($user);
        $version = $program->versions()->first();

        $this->info("Installed: {$program->name} (program_id={$program->id})");
        $this->line("  version {$version->version_number}: {$version->starts_on->toDateString()} 〜 {$version->ends_on->toDateString()}");
        $this->line("  phases={$version->phases()->count()} weeks={$version->weeks()->count()} days={$version->dayTemplates()->count()} constraints={$version->constraints()->count()}");

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $userId = $this->argument('userId');

        if ($userId !== null) {
            $user = User::find($userId);

            if ($user === null) {
                $this->error('User not found.');
            }

            return $user;
        }

        if (User::query()->count() !== 1) {
            $this->error('userId is required when there is not exactly one user.');

            return null;
        }

        return User::query()->sole();
    }
}
