<?php

namespace App\Domain\Shared\AI;

use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
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
        $httpAttempted = false;

        try {
            $inFlight = $this->ledger->markInFlight($usageRequest->id);
            if ($inFlight->status !== AiUsageRequestStatus::InFlight) {
                throw new RuntimeException(
                    "AI usage request [{$usageRequest->id}] is not in_flight; refusing provider HTTP."
                );
            }
            // Only after a confirmed in_flight reservation may HTTP begin.
            $httpAttempted = true;

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

            $data = $response->json();
            if (! is_array($data)) {
                Log::warning('AI provider response body was not a JSON object.', [
                    'usage_request_id' => $usageRequest->id,
                    'user_id' => $userId,
                    'feature' => $feature,
                    'http_status' => $response->status(),
                ]);

                throw new RuntimeException('AI API response is missing valid usage.');
            }

            /** @var mixed $usage */
            $usage = $data['usage'] ?? null;
            $tokens = $this->parseUsageTokens($usage);
            if ($tokens === null) {
                Log::warning('AI provider response missing valid usage tokens.', [
                    'usage_request_id' => $usageRequest->id,
                    'user_id' => $userId,
                    'feature' => $feature,
                    'http_status' => $response->status(),
                ]);

                // Billing outcome unknown: leave in_flight for the reaper to expire.
                throw new RuntimeException('AI API response is missing valid usage.');
            }

            [$inputTokens, $outputTokens] = $tokens;
            $actual = $this->costs->actualCost($model, $inputTokens, $outputTokens);

            $this->ledger->settle($usageRequest->id, $actual, $inputTokens, $outputTokens);

            if ($actual->greaterThan($estimated)) {
                Log::critical('AI actual cost exceeded reserved estimate.', [
                    'usage_request_id' => $usageRequest->id,
                    'user_id' => $userId,
                    'feature' => $feature,
                    'model' => $model,
                    'estimated_usd' => $estimated->toString(),
                    'actual_usd' => $actual->toString(),
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                ]);
            }

            $content = $data['content'] ?? [];
            $text = '';
            if (is_array($content)) {
                foreach ($content as $block) {
                    if (! is_array($block)) {
                        continue;
                    }
                    if (($block['type'] ?? null) !== 'text') {
                        continue;
                    }
                    $text .= (string) ($block['text'] ?? '');
                }
            }

            return [
                'text' => $text,
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'usage_request_id' => $usageRequest->id,
            ];
        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($this->shouldReleaseReservation($httpAttempted, $e)) {
                $this->safeRelease($usageRequest, $this->failureCodeFor($e, $httpAttempted));
            }

            if ($e instanceof ConnectionException) {
                throw new RuntimeException('AI API connection failed.', 0, $e);
            }

            if ($e instanceof RequestException) {
                throw new RuntimeException('AI API request failed.', 0, $e);
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

    /**
     * @return array{0: int, 1: int}|null
     */
    private function parseUsageTokens(mixed $usage): ?array
    {
        if (! is_array($usage)) {
            return null;
        }

        if (! array_key_exists('input_tokens', $usage) || ! array_key_exists('output_tokens', $usage)) {
            return null;
        }

        $input = $usage['input_tokens'];
        $output = $usage['output_tokens'];

        if (! $this->isNonNegativeIntToken($input) || ! $this->isNonNegativeIntToken($output)) {
            return null;
        }

        return [(int) $input, (int) $output];
    }

    private function isNonNegativeIntToken(mixed $value): bool
    {
        if (is_int($value)) {
            return $value >= 0;
        }

        return is_string($value) && preg_match('/^\d+$/', $value) === 1;
    }

    /**
     * Release only when billing did not occur / request clearly never reached an ambiguous send.
     *
     * Classification uses exception type and HTTP lifecycle flags — not message substrings.
     */
    private function shouldReleaseReservation(bool $httpAttempted, Throwable $e): bool
    {
        if (! $httpAttempted) {
            return true;
        }

        if ($e instanceof RequestException && $e->response !== null) {
            return true;
        }

        return false;
    }

    private function failureCodeFor(Throwable $e, bool $httpAttempted): string
    {
        if (! $httpAttempted) {
            return 'pre_http_failure';
        }

        if ($e instanceof RequestException) {
            return 'provider_http_error';
        }

        if ($e instanceof ConnectionException) {
            return 'connect_failure';
        }

        return 'pre_http_failure';
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
