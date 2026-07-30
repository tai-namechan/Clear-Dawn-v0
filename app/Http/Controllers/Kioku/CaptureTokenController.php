<?php

namespace App\Http\Controllers\Kioku;

use App\Domain\Kioku\IosShortcut\KiokuCaptureTokenService;
use App\Domain\Kioku\Models\KiokuCaptureToken;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CaptureTokenController extends Controller
{
    public function index(Request $request): Response
    {
        $tokens = KiokuCaptureToken::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'token_prefix', 'scope', 'last_used_at', 'revoked_at', 'created_at']);

        return Inertia::render('Kioku/Settings', [
            'captureTokens' => $tokens,
            'iosShortcutEnabled' => (bool) config('kioku.ios_shortcut.enabled', false),
            'plainToken' => $request->session()->pull('kioku_plain_token'),
        ]);
    }

    public function store(Request $request, KiokuCaptureTokenService $tokens): RedirectResponse
    {
        abort_unless(config('kioku.ios_shortcut.enabled', false), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $issued = $tokens->issue($request->user(), $validated['name']);

        // Plain token shown once via flash; never logged.
        return redirect()
            ->route('kioku.settings')
            ->with('kioku_plain_token', $issued['plain']);
    }

    public function destroy(Request $request, string $token, KiokuCaptureTokenService $tokens): RedirectResponse
    {
        abort_unless(config('kioku.ios_shortcut.enabled', false), 404);
        $tokens->revoke($request->user(), $token);

        return redirect()->route('kioku.settings');
    }
}
