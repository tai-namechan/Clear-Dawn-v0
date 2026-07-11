<?php

namespace App\Domain\Connectors\Google;

use App\Domain\Kioku\Models\Connector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Access-token lifecycle for a Google connector. Socialite only handles the
 * OAuth dance; refresh is our responsibility. Runs inside queue jobs only.
 */
class GoogleTokenManager
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const EXPIRY_LEEWAY_SECONDS = 60;

    public function validAccessToken(Connector $connector): string
    {
        if ($this->hasUsableToken($connector)) {
            return (string) $connector->access_token;
        }

        $lock = Cache::lock("google-token-refresh:{$connector->id}", 30);

        return $lock->block(15, function () use ($connector): string {
            // Another worker may have refreshed while we waited for the lock.
            $connector->refresh();
            if ($this->hasUsableToken($connector)) {
                return (string) $connector->access_token;
            }

            return $this->refreshAccessToken($connector);
        });
    }

    private function hasUsableToken(Connector $connector): bool
    {
        return $connector->access_token !== null
            && $connector->token_expires_at !== null
            && $connector->token_expires_at->isAfter(now()->addSeconds(self::EXPIRY_LEEWAY_SECONDS));
    }

    private function refreshAccessToken(Connector $connector): string
    {
        if ($connector->refresh_token === null) {
            $this->markReauthorizationRequired($connector);

            throw new ReauthorizationRequiredException;
        }

        $response = Http::asForm()
            ->connectTimeout(5)
            ->timeout(15)
            ->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'refresh_token',
                'client_id' => (string) config('services.google.client_id'),
                'client_secret' => (string) config('services.google.client_secret'),
                'refresh_token' => $connector->refresh_token,
            ]);

        if ($response->status() === 400 || $response->status() === 401) {
            $error = (string) ($response->json('error') ?? '');
            if ($error === 'invalid_grant') {
                $this->markReauthorizationRequired($connector);

                throw new ReauthorizationRequiredException;
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('Google token refresh failed: HTTP '.$response->status());
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google token refresh returned no access token.');
        }

        $connector->update([
            'access_token' => $accessToken,
            'token_expires_at' => now()->addSeconds($expiresIn),
            // Google may omit refresh_token on refresh; keep the stored one.
            ...(is_string($response->json('refresh_token')) && $response->json('refresh_token') !== ''
                ? ['refresh_token' => $response->json('refresh_token')]
                : []),
        ]);

        return $accessToken;
    }

    private function markReauthorizationRequired(Connector $connector): void
    {
        $connector->update([
            'status' => 'error',
            'last_error_code' => 'reauthorization_required',
            'last_error_at' => now(),
        ]);
    }
}
