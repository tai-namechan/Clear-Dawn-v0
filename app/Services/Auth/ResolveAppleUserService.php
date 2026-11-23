<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Finds or creates the Clear Dawn account behind a verified Apple identity
 * (docs/product/ios-app.md). Accounts are matched by Apple's `sub` only:
 * an existing account with the same email is never linked automatically.
 */
class ResolveAppleUserService
{
    public const DEFAULT_NAME = 'Clear Dawn ユーザー';

    public function handle(SocialiteUser $appleUser, ?string $givenName = null, ?string $familyName = null): User
    {
        $appleId = (string) $appleUser->getId();

        if ($appleId === '') {
            throw ValidationException::withMessages([
                'apple' => 'Apple の認証情報を確認できませんでした。もう一度お試しください。',
            ]);
        }

        $existing = User::query()->where('apple_id', $appleId)->first();

        if ($existing !== null) {
            return $existing;
        }

        $email = Str::lower(trim((string) $appleUser->getEmail()));

        if ($email === '') {
            throw ValidationException::withMessages([
                'apple' => 'Apple からメールアドレスを受け取れませんでした。設定 > Apple ID > サインインとセキュリティ から Clear Dawn の連携を解除して、もう一度お試しください。',
            ]);
        }

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'apple' => 'このメールアドレスはすでに登録されています。メールアドレスとパスワードでログインしてください。',
            ]);
        }

        $user = new User;
        $user->forceFill([
            'name' => $this->displayName($appleUser, $givenName, $familyName),
            'email' => $email,
            'apple_id' => $appleId,
            // Apple-only accounts never use it; a password can be set later via reset.
            'password' => Hash::make(Str::random(64)),
        ])->save();

        return $user;
    }

    private function displayName(SocialiteUser $appleUser, ?string $givenName, ?string $familyName): string
    {
        // Japanese order: family name first.
        $fromNative = trim(trim((string) $familyName).' '.trim((string) $givenName));

        if ($fromNative !== '') {
            return Str::limit($fromNative, 255, '');
        }

        $fromApple = trim((string) $appleUser->getName());

        return $fromApple !== '' ? Str::limit($fromApple, 255, '') : self::DEFAULT_NAME;
    }
}
