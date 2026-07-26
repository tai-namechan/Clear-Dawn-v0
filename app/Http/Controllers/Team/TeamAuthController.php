<?php

namespace App\Http\Controllers\Team;

use App\Enums\TeamMembershipRole;
use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use App\Models\TeamUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as OAuthUser;
use Throwable;

class TeamAuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Team/Auth/Login', ['googleEnabled' => (bool) config('services.google_team_auth.enabled'), 'demoEnabled' => app()->isLocal()]);
    }

    public function redirect(): RedirectResponse
    {
        abort_unless((bool) config('services.google_team_auth.enabled'), 404);

        return $this->provider()->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless((bool) config('services.google_team_auth.enabled'), 404);

        try {
            /** @var OAuthUser $oauthUser */
            $oauthUser = $this->provider()->user();
        } catch (Throwable) {
            return redirect()->route('team.login')->withErrors(['google' => 'Googleログインを完了できませんでした。']);
        }

        $subject = (string) $oauthUser->getId();
        $email = mb_strtolower(trim((string) $oauthUser->getEmail()));
        $verified = filter_var($oauthUser->user['email_verified'] ?? false, FILTER_VALIDATE_BOOL);
        abort_if($subject === '' || $email === '' || ! $verified, 403, '確認済みGoogleアカウントが必要です。');

        $teamUser = DB::transaction(function () use ($oauthUser, $subject, $email): ?TeamUser {
            $teamUser = TeamUser::query()->where('google_subject', $subject)->lockForUpdate()->first();
            $invitation = TeamInvitation::query()->where('invitee_type', 'team_user')->whereRaw('LOWER(email) = ?', [$email])->whereNull('accepted_at')->where('expires_at', '>', now())->lockForUpdate()->first();

            if ($teamUser === null && $invitation === null) {
                return null;
            }

            $teamUser ??= new TeamUser(['google_subject' => $subject]);
            $teamUser->fill(['email' => $email, 'name' => (string) $oauthUser->getName(), 'avatar_url' => $oauthUser->getAvatar(), 'last_authenticated_at' => now(), 'status' => 'active'])->save();

            if ($invitation !== null) {
                TeamMembership::query()->firstOrCreate(['team_id' => $invitation->team_id, 'member_type' => 'team_user', 'member_id' => $teamUser->id, 'role' => $invitation->role], ['status' => 'active', 'joined_at' => now(), 'invited_by_team_user_id' => $invitation->invited_by_team_user_id]);
                $invitation->update(['accepted_at' => now(), 'accepted_member_type' => 'team_user', 'accepted_member_id' => $teamUser->id]);
            }

            $hasActiveTeam = TeamMembership::query()
                ->where('member_type', 'team_user')
                ->where('member_id', $teamUser->id)
                ->where('status', 'active')
                ->whereIn('role', TeamMembershipRole::staffValues())
                ->whereHas('team', fn ($query) => $query->where('status', 'active'))
                ->exists();

            return $hasActiveTeam ? $teamUser : null;
        });

        if ($teamUser === null) {
            return redirect()->route('team.login')->withErrors(['google' => '有効なチーム招待または所属が見つかりませんでした。管理者へ確認してください。']);
        }

        Auth::guard('team')->login($teamUser);
        $request->session()->regenerate();

        return redirect()->route('team.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('team')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('team.login');
    }

    private function provider(): GoogleProvider
    {
        /** @var GoogleProvider $provider */
        $provider = Socialite::buildProvider(GoogleProvider::class, config('services.google_team_auth'));

        return $provider;
    }
}
