<?php

namespace App\Domain\Shared\AI;

use App\Domain\Shared\Models\AiUsageLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AiGateway
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{text: string, model: string, input_tokens: int, output_tokens: int}
     */
    public function complete(
        int $userId,
        string $feature,
        PromptTemplate $prompt,
        string $tier = 'cheap',
        int $maxTokens = 1024,
        array $messages = [],
    ): array {
        $this->assertWithinQuota($userId);

        $apiKey = config('ai.anthropic.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('AI API key is not configured.');
        }

        $model = (string) config("ai.models.{$tier}", config('ai.models.cheap'));
        $payloadMessages = $messages !== []
            ? $messages
            : [['role' => 'user', 'content' => $prompt->render()]];

        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $payloadMessages,
        ];

        if ($messages !== [] && $prompt->fixedPrefix !== '') {
            $body['system'] = $prompt->fixedPrefix;
        }

        $response = Http::baseUrl((string) config('ai.anthropic.base_url'))
            ->timeout((int) config('ai.timeout', 60))
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => (string) config('ai.anthropic.version'),
                'content-type' => 'application/json',
            ])
            ->post('/messages', $body);

        if ($response->failed()) {
            throw new RuntimeException('AI API request failed: '.$response->status());
        }

        /** @var array{content?: list<array{type?: string, text?: string}>, usage?: array{input_tokens?: int, output_tokens?: int}} $data */
        $data = $response->json();
        $text = collect($data['content'] ?? [])
            ->filter(fn ($block) => ($block['type'] ?? null) === 'text')
            ->map(fn ($block) => (string) ($block['text'] ?? ''))
            ->implode('');

        $inputTokens = (int) ($data['usage']['input_tokens'] ?? 0);
        $outputTokens = (int) ($data['usage']['output_tokens'] ?? 0);

        $this->logUsage($userId, $feature, $model, $inputTokens, $outputTokens);

        return [
            'text' => $text,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ];
    }

    public function assertWithinQuota(int $userId): void
    {
        $limit = (float) config('ai.limits.monthly_usd_per_user', 10);
        $spent = (float) AiUsageLog::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('estimated_cost_usd');

        if ($spent >= $limit) {
            throw new RuntimeException('AI monthly usage limit exceeded.');
        }
    }

    public function logUsage(
        int $userId,
        string $feature,
        string $model,
        int $inputTokens,
        int $outputTokens,
    ): void {
        $pricing = config("ai.pricing.{$model}", config('ai.pricing.default'));
        $inputRate = (float) ($pricing['input'] ?? 3.0);
        $outputRate = (float) ($pricing['output'] ?? 15.0);
        $cost = ($inputTokens / 1_000_000) * $inputRate + ($outputTokens / 1_000_000) * $outputRate;

        AiUsageLog::query()->withoutUserScope()->create([
            'user_id' => $userId,
            'feature' => $feature,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost_usd' => round($cost, 4),
            'created_at' => now(),
        ]);
    }
}
