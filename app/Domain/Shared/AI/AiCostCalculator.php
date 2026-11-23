<?php

namespace App\Domain\Shared\AI;

use Illuminate\Support\Facades\Log;

final class AiCostCalculator
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function estimateReservation(string $model, array $payload, int $maxTokens, int $inputBuffer = 0): AiMoney
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode AI payload for cost estimation.');
        }

        $rates = $this->ratesFor($model);

        return AiMoney::estimateFromTokensAndRates(
            strlen($json) + $inputBuffer,
            $maxTokens,
            $rates['input'],
            $rates['output'],
        );
    }

    public function actualCost(string $model, int $inputTokens, int $outputTokens): AiMoney
    {
        $rates = $this->ratesFor($model);

        return AiMoney::estimateFromTokensAndRates(
            $inputTokens,
            $outputTokens,
            $rates['input'],
            $rates['output'],
        );
    }

    public function monthlyLimit(): AiMoney
    {
        $limit = config('ai.limits.monthly_usd_per_user', '10');

        if (is_int($limit)) {
            return AiMoney::of($limit);
        }

        if (is_float($limit)) {
            return AiMoney::of(rtrim(rtrim(sprintf('%.10F', $limit), '0'), '.') ?: '0');
        }

        if (is_string($limit) && is_numeric($limit)) {
            return AiMoney::of($limit);
        }

        return AiMoney::of('10');
    }

    /**
     * 価格表に無いモデルは default（$3/$15）へフォールバックする。
     *
     * フォールバック自体は必要だが、無言で行うと「月次上限 $10」が実際の
     * 金額を意味しなくなったことに誰も気づけない。モデルを差し替えて
     * config/ai.php の pricing 更新を忘れた場合を検知できるよう警告を残す
     * （docs/audit/2026-07-26-pre-release-audit.md H-5）。
     *
     * 課金経路はホットパスなので、同一モデルにつき1プロセス1回だけ出す。
     *
     * @return array{input: string, output: string}
     */
    public function ratesFor(string $model): array
    {
        $pricing = config("ai.pricing.{$model}");

        if (! is_array($pricing)) {
            $this->warnOnceAboutMissingPricing($model);

            $pricing = config('ai.pricing.default');
        }

        $input = is_array($pricing) ? ($pricing['input'] ?? 3.0) : 3.0;
        $output = is_array($pricing) ? ($pricing['output'] ?? 15.0) : 15.0;

        return [
            'input' => $this->rateToString($input),
            'output' => $this->rateToString($output),
        ];
    }

    /**
     * @var array<string, true>
     */
    private array $warnedModels = [];

    private function warnOnceAboutMissingPricing(string $model): void
    {
        if (isset($this->warnedModels[$model])) {
            return;
        }

        $this->warnedModels[$model] = true;

        Log::warning('AI pricing table has no entry for model; falling back to default rates.', [
            'model' => $model,
            'hint' => 'config/ai.php の pricing に該当モデルを追加してください。'
                .'未追加のままだと月次上限が実際の金額とずれます。',
        ]);
    }

    private function rateToString(mixed $rate): string
    {
        if (is_int($rate)) {
            return (string) $rate;
        }

        if (is_float($rate)) {
            return rtrim(rtrim(sprintf('%.10F', $rate), '0'), '.') ?: '0';
        }

        if (is_string($rate) && is_numeric($rate)) {
            return $rate;
        }

        return '0';
    }
}
