<?php

namespace App\Http\Middleware;

use App\Domain\Kioku\IosShortcut\KiokuCaptureTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateKiokuCaptureToken
{
    public function __construct(private KiokuCaptureTokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('kioku.ios_shortcut.enabled', false) && ! config('kioku.ios_shortcut.capture_enabled', false)) {
            abort(404);
        }

        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            abort(401);
        }

        $plain = trim(substr($header, 7));
        $token = $this->tokens->findActiveByPlain($plain);
        if ($token === null) {
            abort(401);
        }

        $user = $token->user()->first();
        if ($user === null) {
            abort(401);
        }

        $this->tokens->touch($token);
        $request->setUserResolver(static fn () => $user);
        $request->attributes->set('kioku_capture_token_id', $token->id);

        return $next($request);
    }
}
