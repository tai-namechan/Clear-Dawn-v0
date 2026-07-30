<?php

namespace App\Domain\Kioku\Embedding;

use App\Domain\Shared\AI\AiCostCalculator;
use App\Domain\Shared\AI\AiUsageLedger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OpenAiEmbeddingGateway implements EmbeddingGateway
{
    public const FEATURE = 'kioku.embedding';

    public function __construct(
        private AiUsageLedger $ledger,
        private AiCostCalculator $costs,
    ) {}

    public function embed(EmbeddingRequest $request): EmbeddingResult
    {
        $apiKey = config('kioku.embedding.api_key') ?: config('services.openai.key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new EmbeddingFailedException('OpenAI embedding API key is not configured.', true, 'missing_api_key');
        }

        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $estimatedTokens = max(1, (int) ceil(mb_strlen($request->text) / 4));
        $estimated = $this->costs->actualCost($request->model, $estimatedTokens * 2, 0);

        $usageRequest = $this->ledger->reserve(
            userId: $request->userId,
            feature: self::FEATURE,
            model: $request->model,
            estimated: $estimated,
        );

        try {
            $this->ledger->markInFlight($usageRequest->id);

            $payload = [
                'model' => $request->model,
                'input' => $request->text,
            ];
            if ($request->dimensions !== null) {
                $payload['dimensions'] = $request->dimensions;
            }

            $response = Http::withToken($apiKey)
                ->timeout((int) config('ai.timeout', 60))
                ->connectTimeout((int) config('ai.connect_timeout', 10))
                ->acceptJson()
                ->post($baseUrl.'/embeddings', $payload);

            if ($response->status() === 400 || $response->status() === 401 || $response->status() === 403) {
                $this->ledger->release($usageRequest->id, 'provider_rejected');
                throw new EmbeddingFailedException(
                    'Embedding provider rejected the request.',
                    true,
                    'provider_rejected',
                );
            }

            if (! $response->successful()) {
                $this->ledger->release($usageRequest->id, 'provider_http_error');
                throw new EmbeddingFailedException(
                    'Embedding provider HTTP '.$response->status(),
                    false,
                    'provider_http_error',
                );
            }

            $json = $response->json();
            $vector = $json['data'][0]['embedding'] ?? null;
            if (! is_array($vector) || $vector === []) {
                $this->ledger->release($usageRequest->id, 'empty_vector');
                throw new EmbeddingFailedException('Embedding provider returned an empty vector.', true, 'empty_vector');
            }

            /** @var list<float> $floats */
            $floats = array_map(static fn ($v): float => (float) $v, $vector);
            $inputTokens = (int) ($json['usage']['prompt_tokens'] ?? $json['usage']['total_tokens'] ?? $estimatedTokens);
            $actual = $this->costs->actualCost($request->model, $inputTokens, 0);
            $this->ledger->settle($usageRequest->id, $actual, $inputTokens, 0);

            return new EmbeddingResult(
                vector: $floats,
                model: (string) ($json['model'] ?? $request->model),
                dimensions: count($floats),
                inputTokens: $inputTokens,
                requestId: $response->header('x-request-id') ?: null,
                actualUsd: $actual->toString(),
                provider: 'openai',
            );
        } catch (EmbeddingFailedException $e) {
            throw $e;
        } catch (ConnectionException $e) {
            $this->safeRelease($usageRequest->id, 'connection_error');
            throw new EmbeddingFailedException($e->getMessage(), false, 'connection_error');
        } catch (Throwable $e) {
            $this->safeRelease($usageRequest->id, 'unexpected_error');
            Log::warning('OpenAiEmbeddingGateway failed', [
                'memory_id' => $request->memoryId,
                'error_code' => 'unexpected_error',
            ]);
            throw new EmbeddingFailedException('Unexpected embedding failure.', false, 'unexpected_error');
        }
    }

    private function safeRelease(string $requestId, string $code): void
    {
        try {
            $this->ledger->release($requestId, $code);
        } catch (Throwable) {
            // already settled/released
        }
    }
}
