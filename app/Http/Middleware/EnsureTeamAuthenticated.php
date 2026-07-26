<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamAuthenticated
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $teamUser = auth('team')->user();

        if ($teamUser === null) {
            return redirect()->route('team.login');
        }

        abort_unless($teamUser->status === 'active', 403, 'このチームアカウントは利用できません。');

        return $next($request);
    }
}
