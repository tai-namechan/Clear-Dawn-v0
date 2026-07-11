<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Services\MemoryClassifier;
use App\Domain\Shared\AI\AiGateway;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

/**
 * Regression eval for the kioku classify prompt.
 *
 * Calls the real AI API (costs money, writes ai_usage_logs) against the
 * fixture cases and reports per-case pass/fail. Run after every classify
 * prompt change; add a failing case to the fixture before fixing the prompt.
 */
class KiokuClassifyEvalCommand extends Command
{
    protected $signature = 'kioku:eval-classify
        {--user= : AiGateway に渡すユーザーID（省略時は最初のユーザー）}
        {--fixture=tests/Fixtures/kioku-classify-eval.json : evalケースのJSONパス（base_path相対）}
        {--yes : 課金確認をスキップ}';

    protected $description = 'classifyプロンプトの回帰evalを実AIで実行する';

    public function handle(AiGateway $ai, MemoryClassifier $classifier): int
    {
        $fixturePath = base_path((string) $this->option('fixture'));
        if (! is_file($fixturePath)) {
            $this->error("Fixtureが見つかりません: {$fixturePath}");

            return self::FAILURE;
        }

        /** @var array{cases?: list<array{name: string, raw_content: string, expected_types: list<string>, expect_japanese?: bool}>} $fixture */
        $fixture = json_decode((string) file_get_contents($fixturePath), true) ?: [];
        $cases = $fixture['cases'] ?? [];
        if ($cases === []) {
            $this->error('evalケースが空です。');

            return self::FAILURE;
        }

        $userId = $this->option('user') !== null
            ? (int) $this->option('user')
            : (int) User::query()->orderBy('id')->value('id');
        if ($userId === 0) {
            $this->error('ユーザーが存在しません。--user を指定してください。');

            return self::FAILURE;
        }

        $count = count($cases);
        if (! $this->option('yes') && ! $this->confirm("実AI APIを{$count}回呼び出し、課金が発生します。続行しますか?")) {
            return self::SUCCESS;
        }

        $rows = [];
        $failures = 0;

        foreach ($cases as $case) {
            try {
                $result = $classifier->classify($ai, $userId, $case['raw_content']);
            } catch (Throwable $e) {
                $failures++;
                $rows[] = [$case['name'], 'ERROR', implode('|', $case['expected_types']), $e->getMessage(), 'FAIL'];

                continue;
            }

            $reasons = [];
            if (! in_array($result['memory_type'], $case['expected_types'], true)) {
                $reasons[] = "type={$result['memory_type']}";
            }
            if (($case['expect_japanese'] ?? false) && ! $this->containsJapanese((string) $result['title'])) {
                $reasons[] = 'title not ja';
            }

            if ($reasons !== []) {
                $failures++;
            }

            $rows[] = [
                $case['name'],
                $result['memory_type'],
                implode('|', $case['expected_types']),
                (string) $result['title'],
                $reasons === [] ? 'PASS' : 'FAIL ('.implode(', ', $reasons).')',
            ];
        }

        $this->table(['ケース', '判定type', '期待type', 'title', '結果'], $rows);
        $passed = $count - $failures;
        $this->info("合格: {$passed}/{$count}");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function containsJapanese(string $text): bool
    {
        return preg_match('/[\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}]/u', $text) === 1;
    }
}
