<?php

namespace App\Domain\Kioku\IosShortcut;

use App\Domain\Kioku\Models\KiokuCaptureToken;
use App\Models\User;
use Illuminate\Support\Str;

final class KiokuCaptureTokenService
{
    /**
     * @return array{token: KiokuCaptureToken, plain: string}
     */
    public function issue(User $user, string $name): array
    {
        $plain = 'cdkioku_'.Str::random(48);
        $token = KiokuCaptureToken::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'token_prefix' => substr($plain, 0, 12),
            'scope' => 'kioku:capture',
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    public function revoke(User $user, string $tokenId): bool
    {
        $token = KiokuCaptureToken::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->whereKey($tokenId)
            ->whereNull('revoked_at')
            ->first();

        if ($token === null) {
            return false;
        }

        $token->update(['revoked_at' => now()]);

        return true;
    }

    public function findActiveByPlain(string $plain): ?KiokuCaptureToken
    {
        if ($plain === '' || ! str_starts_with($plain, 'cdkioku_')) {
            return null;
        }

        $token = KiokuCaptureToken::query()
            ->withoutUserScope()
            ->where('token_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->first();

        if ($token === null || ! $token->isActive()) {
            return null;
        }

        return $token;
    }

    public function touch(KiokuCaptureToken $token): void
    {
        $token->forceFill(['last_used_at' => now()])->save();
    }
}
