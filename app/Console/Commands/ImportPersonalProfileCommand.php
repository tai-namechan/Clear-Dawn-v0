<?php

namespace App\Console\Commands;

use App\Models\PersonalProfileEntry;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 個人プロファイル（1RM・既往・安全方針等）を JSON から投入する（ADR-0012）。
 * 個人実測値はリポジトリに含めないため、既定パスは gitignore 済みの personal/ 配下。
 *
 * JSON 形式: [{"key": "one_rm_bench", "value_numeric": 57, "unit": "kg",
 *   "effective_from": "2026-07-16", "source": "測定", "note": "..."}, ...]
 * value は value_numeric / value_text / value_json のいずれか1つ以上。
 * 同一 (key, effective_from) は上書き（それ以外の履歴行は不変）。
 */
class ImportPersonalProfileCommand extends Command
{
    protected $signature = 'cleardawn:import-personal
        {userId : users.id}
        {--path=personal/profile.json : JSON ファイルパス（リポジトリルート相対 or 絶対）}';

    protected $description = '個人プロファイル値（personal_profile_entries）を JSON から投入する';

    public function handle(): int
    {
        $user = User::find($this->argument('userId'));

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $path = $this->option('path');

        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $entries = json_decode((string) file_get_contents($path), true);

        if (! is_array($entries)) {
            $this->error('Invalid JSON: expected an array of entries.');

            return self::FAILURE;
        }

        $imported = 0;

        foreach ($entries as $index => $entry) {
            if (! is_array($entry) || ! isset($entry['key'], $entry['effective_from'])) {
                $this->error("Entry #{$index}: key and effective_from are required.");

                return self::FAILURE;
            }

            if (! isset($entry['value_numeric']) && ! isset($entry['value_text']) && ! isset($entry['value_json'])) {
                $this->error("Entry #{$index} ({$entry['key']}): one of value_numeric / value_text / value_json is required.");

                return self::FAILURE;
            }

            PersonalProfileEntry::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'key' => $entry['key'],
                    'effective_from' => Carbon::parse($entry['effective_from'])->startOfDay(),
                ],
                [
                    'value_numeric' => $entry['value_numeric'] ?? null,
                    'value_text' => $entry['value_text'] ?? null,
                    'value_json' => $entry['value_json'] ?? null,
                    'unit' => $entry['unit'] ?? null,
                    'source' => $entry['source'] ?? null,
                    'note' => $entry['note'] ?? null,
                ],
            );

            $imported++;
        }

        $this->info("Imported {$imported} entries for user {$user->id}.");

        return self::SUCCESS;
    }
}
