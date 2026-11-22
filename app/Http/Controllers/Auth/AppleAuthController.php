<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\ResolveAppleUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use SocialiteProviders\Apple\Provider as AppleProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Sign in with Apple (docs/product/ios-app.md).
 * Web: redirect + form_post callback. iOS app: native sheet → identity token.
 */
class AppleAuthController extends Controller
{
    private const NATIVE_NONCE_SESSION_KEY = 'apple_native_nonce';

    public function redirect(): SymfonyRedirectResponse
    {
        abort_unless(self::enabled(), 404);

        return $this->provider()->stateless()->cookieNonce()->redirect();
    }

    public function callback(Request $request, ResolveAppleUserService $resolver): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        if ($request->filled('error')) {
            // The user closed or declined Apple's sign-in page.
            return redirect()->route('login');
        }

        try {
            $appleUser = $this->provider()->stateless()->cookieNonce()->user();
            $user = $resolver->handle($appleUser, ...$this->namesFromCallback($request));
        } catch (ValidationException $exception) {
            return redirect()->route('login')->withErrors($exception->errors());
        } catch (InvalidStateException) {
            return redirect()->route('login')->withErrors([
                'apple' => 'Apple でのサインインの有効期限が切れました。もう一度お試しください。',
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Single-use nonce the iOS app passes to Apple; the identity token must echo it.
     */
    public function nativeNonce(Request $request): JsonResponse
    {
        abort_unless(self::enabled(), 404);

        $nonce = Str::random(40);
        $request->session()->put(self::NATIVE_NONCE_SESSION_KEY, $nonce);

        return response()->json(['nonce' => $nonce]);
    }

    public function native(Request $request, ResolveAppleUserService $resolver): JsonResponse
    {
        abort_unless(self::enabled(), 404);

        /** @var array{identity_token: string, given_name?: string|null, family_name?: string|null} $validated */
        $validated = $request->validate([
            'identity_token' => ['required', 'string', 'max:8192'],
            'given_name' => ['nullable', 'string', 'max:100'],
            'family_name' => ['nullable', 'string', 'max:100'],
        ]);

        $nonce = $request->session()->pull(self::NATIVE_NONCE_SESSION_KEY);

        if (! is_string($nonce) || $nonce === '') {
            throw ValidationException::withMessages([
                'apple' => 'Apple でのサインインをやり直してください。',
            ]);
        }

        try {
            $appleUser = $this->provider()->userByIdentityToken($validated['identity_token'], $nonce);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'apple' => 'Apple の認証情報を確認できませんでした。もう一度お試しください。',
            ]);
        }

        $user = $resolver->handle(
            $appleUser,
            $validated['given_name'] ?? null,
            $validated['family_name'] ?? null,
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json(['redirect' => route('dashboard', absolute: false)]);
    }

    /**
     * The button is shown only where Apple credentials are configured.
     */
    public static function enabled(): bool
    {
        return filled(config('services.apple.client_id'));
    }

    private function provider(): AppleProvider
    {
        /** @var AppleProvider $provider */
        $provider = Socialite::driver('apple');

        return $provider;
    }

    /**
     * Apple posts the user's name only on the very first authorization.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function namesFromCallback(Request $request): array
    {
        $payload = json_decode((string) $request->input('user', ''), true);

        if (! is_array($payload)) {
            return [null, null];
        }

        return [
            is_string($payload['name']['firstName'] ?? null) ? $payload['name']['firstName'] : null,
            is_string($payload['name']['lastName'] ?? null) ? $payload['name']['lastName'] : null,
        ];
    }
}
