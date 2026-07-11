<?php

namespace App\Http\Controllers\Yoyu;

use App\Domain\Connectors\Calendar\CalendarSyncCoordinator;
use App\Domain\Connectors\Jobs\DisconnectGoogleCalendarJob;
use App\Domain\Connectors\Jobs\SyncGoogleCalendarJob;
use App\Domain\Kioku\Models\Connector;
use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as OAuthUser;
use Throwable;

class CalendarConnectionController extends Controller
{
    private const CALENDAR_SCOPE = 'https://www.googleapis.com/auth/calendar.readonly';

    public function settings(Request $request): Response
    {
        $connector = $this->connectorFor($request);

        return Inertia::render('Yoyu/Settings', [
            'calendarConnection' => $this->connectionProps($connector),
            'googleEnabled' => (bool) config('services.google.calendar_enabled'),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        abort_unless((bool) config('services.google.calendar_enabled'), 404);

        $driver = Socialite::driver('google');
        abort_unless($driver instanceof AbstractProvider, 500);

        return $driver
            ->scopes([self::CALENDAR_SCOPE])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent select_account',
                'include_granted_scopes' => 'true',
            ])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless((bool) config('services.google.calendar_enabled'), 404);

        try {
            /** @var OAuthUser $oauthUser */
            $oauthUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            // Denied grant / invalid state. No provider details leak to the UI.
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Google連携がキャンセルされました。']);

            return redirect()->route('yoyu.settings');
        }

        if (! $this->calendarScopeGranted($oauthUser)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'カレンダーの読み取り権限が許可されませんでした。']);

            return redirect()->route('yoyu.settings');
        }

        $user = $request->user();
        $externalId = (string) $oauthUser->getId();
        // Socialite's PHPDoc types are optimistic; treat provider values as untrusted.
        $refreshToken = $this->nullableString($oauthUser->refreshToken ?: null);

        $connector = DB::transaction(function () use ($user, $oauthUser, $externalId, $refreshToken): ?Connector {
            $existing = Connector::query()
                ->withoutUserScope()
                ->where('user_id', $user->id)
                ->where('source_type', Connector::SOURCE_GOOGLE_CALENDAR)
                ->lockForUpdate()
                ->first();

            $sameAccount = $existing !== null && $existing->external_account_id === $externalId;

            if ($existing !== null && ! $sameAccount) {
                // Account switch: the old account's cache and tokens are dead.
                YoyuCalendarEvent::query()
                    ->withoutUserScope()
                    ->where('connector_id', $existing->id)
                    ->delete();
                $existing->update(['refresh_token' => null]);
            }

            $effectiveRefresh = $refreshToken ?? ($sameAccount ? $existing->refresh_token : null);

            if ($effectiveRefresh === null) {
                // Cannot sync in the background without a refresh token.
                Connector::query()->withoutUserScope()->updateOrCreate(
                    ['user_id' => $user->id, 'source_type' => Connector::SOURCE_GOOGLE_CALENDAR],
                    [
                        'external_account_id' => $externalId,
                        'external_account_email' => $oauthUser->getEmail(),
                        'access_token' => null,
                        'refresh_token' => null,
                        'token_expires_at' => null,
                        'status' => 'error',
                        'last_error_code' => 'reauthorization_required',
                        'last_error_at' => now(),
                    ],
                );

                return null;
            }

            return Connector::query()->withoutUserScope()->updateOrCreate(
                ['user_id' => $user->id, 'source_type' => Connector::SOURCE_GOOGLE_CALENDAR],
                [
                    'external_account_id' => $externalId,
                    'external_account_email' => $oauthUser->getEmail(),
                    'access_token' => $oauthUser->token,
                    'refresh_token' => $effectiveRefresh,
                    'token_expires_at' => now()->addSeconds($this->positiveIntOr($oauthUser->expiresIn, 3600)),
                    'scopes' => $this->stringList($oauthUser->approvedScopes, [self::CALENDAR_SCOPE]),
                    'status' => 'syncing',
                    'last_error_code' => null,
                    'last_error_at' => null,
                ],
            );
        });

        if ($connector === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Googleから更新用トークンを取得できませんでした。もう一度接続してください。']);

            return redirect()->route('yoyu.settings');
        }

        SyncGoogleCalendarJob::dispatch($connector->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Googleカレンダーを接続しました。同期しています…']);

        return redirect()->route('yoyu.settings');
    }

    public function sync(Request $request, CalendarSyncCoordinator $coordinator): RedirectResponse
    {
        $dispatched = $coordinator->forceSync($request->user());

        Inertia::flash('toast', $dispatched
            ? ['type' => 'success', 'message' => '同期を開始しました。']
            : ['type' => 'info', 'message' => 'Googleカレンダーが接続されていません。']);

        return redirect()->route('yoyu.settings');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $connector = $this->connectorFor($request);

        if ($connector !== null) {
            $updated = Connector::query()
                ->withoutUserScope()
                ->whereKey($connector->id)
                ->where('status', '!=', 'revoking')
                ->update(['status' => 'revoking']);

            if ($updated === 1) {
                DisconnectGoogleCalendarJob::dispatch($connector->id);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Googleカレンダーの接続を解除しています…']);

        return redirect()->route('yoyu.settings');
    }

    private function connectorFor(Request $request): ?Connector
    {
        return Connector::query()
            ->withoutUserScope()
            ->where('user_id', $request->user()->id)
            ->where('source_type', Connector::SOURCE_GOOGLE_CALENDAR)
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function connectionProps(?Connector $connector): ?array
    {
        if ($connector === null) {
            return null;
        }

        return [
            'status' => $connector->status,
            'account_email' => $connector->external_account_email,
            'last_synced_at' => $connector->last_synced_at?->toIso8601String(),
            'last_error_code' => $connector->last_error_code,
        ];
    }

    private function calendarScopeGranted(OAuthUser $oauthUser): bool
    {
        $scopes = $this->stringList($oauthUser->approvedScopes, []);
        if ($scopes === []) {
            return true; // Provider did not report scopes; token validity is checked at sync time.
        }

        return in_array(self::CALENDAR_SCOPE, $scopes, true);
    }

    /**
     * Socialite property PHPDoc is optimistic; treat provider values as untrusted.
     */
    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function positiveIntOr(mixed $value, int $default): int
    {
        return is_int($value) && $value > 0 ? $value : $default;
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private function stringList(mixed $value, array $default): array
    {
        if (! is_array($value)) {
            return $default;
        }

        $strings = array_values(array_filter($value, fn ($item): bool => is_string($item)));

        return $strings === [] ? $default : $strings;
    }
}
