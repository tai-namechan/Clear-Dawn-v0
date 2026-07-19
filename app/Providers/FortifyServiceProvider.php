<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->throttleSensitiveGuestRoutes();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => config('app.public_signup_enabled') && Features::enabled(Features::resetPasswords()),
            'canRegister' => config('app.public_signup_enabled') && Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });

        // 新規登録: 正規利用は1回きりの操作のため IP あたり緩やかな上限で十分。
        // 大量アカウント作成・登録フォーム連打を抑止する。
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        // パスワード再設定（送信/確定の両方）: 特定メールアドレスへのメール爆撃、
        // および再設定トークンの総当たりを抑止する。email+IP の組み合わせでキー化。
        RateLimiter::for('password-reset', function (Request $request) {
            $email = Str::transliterate(Str::lower((string) $request->input('email')));

            return Limit::perMinutes(15, 5)->by($email.'|'.$request->ip());
        });
    }

    /**
     * Fortify がデフォルトで throttle を付与しない register / password-reset の
     * state-changing ルートに、登録済みルート解決後（すべてのプロバイダの boot 完了後）
     * throttle ミドルウェアを後付けする。Fortify のルート定義自体は変更しない。
     */
    private function throttleSensitiveGuestRoutes(): void
    {
        $this->app->booted(function (): void {
            $targets = array_filter([
                'register.store' => config('fortify.limiters.register'),
                'password.email' => config('fortify.limiters.password-reset'),
                'password.update' => config('fortify.limiters.password-reset'),
            ]);

            $routes = $this->app['router']->getRoutes();
            // 起動直後は Route::name() 由来の名前索引がまだ構築されていない
            // （索引は初回の URL 解決/ディスパッチ時に遅延構築されるため）。
            // ここで明示的に索引を再構築してから名前引きする。
            $routes->refreshNameLookups();

            foreach ($targets as $routeName => $limiter) {
                $routes->getByName($routeName)?->middleware('throttle:'.$limiter);
            }
        });
    }
}
