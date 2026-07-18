<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InstallElevenWeekProgramService;
use Illuminate\Console\Command;

class InstallElevenWeekProgramCommand extends Command
{
    protected $signature = 'cleardawn:install-program {userId : users.id}';

    protected $description = '11週 統合プログラム（筋力・投球・栄養）を登録する（冪等）';

    public function handle(InstallElevenWeekProgramService $service): int
    {
        $user = User::find($this->argument('userId'));

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $program = $service->handle($user);
        $version = $program->versions()->first();

        $this->info("Installed: {$program->name} (program_id={$program->id})");
        $this->line("  version {$version->version_number}: {$version->starts_on->toDateString()} 〜 {$version->ends_on->toDateString()}");
        $this->line("  phases={$version->phases()->count()} weeks={$version->weeks()->count()} days={$version->dayTemplates()->count()} constraints={$version->constraints()->count()}");

        return self::SUCCESS;
    }
}
