<?php

namespace App\Domain\Shared\AI;

use App\Domain\Shared\Models\AiUsageRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class AiGateway
{
    public function __construct(
        private AiUsageLedger $ledger,
        private AiCostCalculator $costs,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{text: string, model: string, input_tokens: int, output_tokens: int, usage_request_id: string}
     */
    public function complete(
        int $userId,
        string $feature,
        PromptTemplate $prompt,
        string $tier = 'cheap',
        int $maxTokens = 1024,
        array $messages = [],
    ): array {
        $apiKey = config('ai.anthropic.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('AI API key is not configured.');
        }

        if ($maxTokens < 1) {
            throw new RuntimeException('max_tokens must be a positive integer before AI reservation.');
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

        $estimated = $this->costs->estimateReservation($model, $body, $maxTokens);
        $usageRequest = $this->ledger->reserve($userId, $feature, $model, $estimated);
        $providerStarted = false;

        try {
            $this->ledger->markInFlight($usageRequest->id);
            $providerStarted = true;

            $response = Http::baseUrl((string) config('ai.anthropic.base_url'))
                ->timeout((int) config('ai.timeout', 60))
                ->connectTimeout((int) config('ai.connect_timeout', 10))
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => (string) config('ai.anthropic.version'),
                    'content-type' => 'application/json',
                ])
                ->post('/messages', $body);

            if ($response->failed()) {
                $this->ledger->release($usageRequest->id, 'provider_http_error');

                throw new RuntimeException('AI API request failed: '.$response->status());
            }

            /** @var array{content?: list<array{type?: string, text?: string}>, usage?: array{input_tokens?: int, output_tokens?: int}} $data */
            $data = $response->json();
            $inputTokens = (int) ($data['usage']['input_tokens'] ?? 0);
            $outputTokens = (int) ($data['usage']['output_tokens'] ?? 0);
            $actual = $this->costs->actualCost($model, $inputTokens, $outputTokens);

            // Settle on usage success even if later response-body handling fails.
            $this->ledger->settle($usageRequest->id, $actual, $inputTokens, $outputTokens);

            if ($actual->greaterThan($estimated)) {
                Log::critical('AI actual cost exceeded reserved estimate.', [
                    'usage_request_id' => $usageRequest->id,
                    'user_id' => $userId,
                    'feature' => $feature,
                    'model' => $model,
                    'estimated_usd' => $estimated->toString(),
                    'actual_usd' => $actual->toString(),
                ]);
            }

            $text = collect($data['content'] ?? [])
                ->filter(fn ($block) => ($block['type'] ?? null) === 'text')
                ->map(fn ($block) => (string) ($block['text'] ?? ''))
                ->implode('');

            return [
                'text' => $text,
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'usage_request_id' => $usageRequest->id,
            ];
        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (ConnectionException $e) {
            if (! $providerStarted) {
                $this->safeRelease($usageRequest, 'connect_failure');
            }
            // After HTTP start, leave in_flight for the reaper to expire conservatively.

            throw new RuntimeException('AI API connection failed.', 0, $e);
        } catch (RequestException $e) {
            $this->safeRelease($usageRequest, 'provider_http_error');

            throw new RuntimeException('AI API request failed.', 0, $e);
        } catch (Throwable $e) {
            if (! $providerStarted) {
                $this->safeRelease($usageRequest, 'pre_http_failure');
            }

            throw $e;
        }
    }

    /**
     * Snapshot check against the monthly ledger (no SUM of logs).
     */
    public function assertWithinQuota(int $userId): void
    {
        $period = app(AiUsagePeriodResolver::class)->periodFor();
        $monthly = $this->ledger->ensureMonthly($userId, $period);
        $used = AiMoney::of((string) $monthly->spent_usd)
            ->add(AiMoney::of((string) $monthly->reserved_usd));
        $limit = $this->costs->monthlyLimit();

        if ($used->greaterThanOrEqual($limit)) {
            throw new QuotaExceededException;
        }
    }

    private function safeRelease(AiUsageRequest $usageRequest, string $failureCode): void
    {
        try {
            $this->ledger->release($usageRequest->id, $failureCode);
        } catch (Throwable $e) {
            Log::warning('Failed to release AI usage reservation.', [
                'usage_request_id' => $usageRequest->id,
                'failure_code' => $failureCode,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
