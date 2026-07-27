<?php

use Laravel\Fortify\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Fortify Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Fortify will use while
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Fortify Password Broker
    |--------------------------------------------------------------------------
    |
    | Here you may specify which password broker Fortify can use when a user
    | is resetting their password. This configured value should match one
    | of your password brokers setup in your "auth" configuration file.
    |
    */

    'passwords' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Username / Email
    |--------------------------------------------------------------------------
    |
    | This value defines which model attribute should be considered as your
    | application's "username" field. Typically, this might be the email
    | address of the users but you are free to change this value here.
    |
    | Out of the box, Fortify expects forgot password and reset password
    | requests to have a field named 'email'. If the application uses
    | another name for the field you may define it below as needed.
    |
    */

    'username' => 'email',

    'email' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Lowercase Usernames
    |--------------------------------------------------------------------------
    |
    | This value defines whether usernames should be lowercased before saving
    | them in the database, as some database system string fields are case
    | sensitive. You may disable this for your application if necessary.
    |
    */

    'lowercase_usernames' => true,

    /*
    |--------------------------------------------------------------------------
    | Home Path
    |--------------------------------------------------------------------------
    |
    | Here you may configure the path where users will get redirected during
    | authentication or password reset when the operations are successful
    | and the user is authenticated. You are free to change this value.
    |
    */

    'home' => '/dashboard',

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Prefix / Subdomain
    |--------------------------------------------------------------------------
    |
    | Here you may specify which prefix Fortify will assign to all the routes
    | that it registers with the application. If necessary, you may change
    | subdomain under which all of the Fortify routes will be available.
    |
    */

    'prefix' => '',

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Middleware
    |--------------------------------------------------------------------------
    |
    | Here you may specify which middleware Fortify will assign to the routes
    | that it registers with the application. If necessary, you may change
    | these middleware but typically this provided default is preferred.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | By default, Fortify will throttle logins to five requests per minute for
    | every email and IP address combination. However, if you would like to
    | specify a custom rate limiter to call then you may specify it here.
    |
    */

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
        'passkeys' => 'passkeys',
        'register' => 'register',
        'password-reset' => 'password-reset',
    ],

    /*
    |--------------------------------------------------------------------------
    | Register View Routes
    |--------------------------------------------------------------------------
    |
    | Here you may specify if the routes returning views should be disabled as
    | you may not need them when building your own application. This may be
    | especially true if you're writing a custom single-page application.
    |
    */

    'views' => true,

    /*
    |--------------------------------------------------------------------------
    | Passkeys
    |--------------------------------------------------------------------------
    |
    | These settings configure Fortify's passkey (WebAuthn) support.
    |
    */

    'passkeys' => [
        'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST),
        'allowed_origins' => [config('app.url')],
        'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),
        'timeout' => 60000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Some of the Fortify features are optional. You may disable the features
    | by removing them from this array. You're free to only remove some of
    | these features, or you can even remove all of these if you need to.
    |
    */

    'features' => [
        // registration は環境によらず常に登録する。
        //
        // APP_PUBLIC_SIGNUP_ENABLED=false は「UI に導線を出さない」ところまでが
        // 現在の要件であり、URL 直打ちでの登録は許容している
        // （docs/product/signup-policy.md）。
        //
        // ここをフラグで出し分けると、Wayfinder が resources/js/routes/register を
        // 生成しなくなり、それを静的 import している AuthRegisterForm.vue /
        // AuthLoginForm.vue が解決できずフロントのビルドごと失敗する。
        // ビルド生成物の形が実行時フラグで変わる構造は持ち込まない。
        //
        // 登録自体を実際に塞ぐ必要が生じたら、ルートを消すのではなく
        // ミドルウェアで拒否する（生成面を env に依存させないため）。
        Features::registration(),

        // パスワード再設定は「既存ユーザーの復旧手段」であり、
        // 新規登録の導線を出すかどうかとは無関係。常に有効にする。
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
            // 'window' => 0
        ]),
        Features::passkeys([
            'confirmPassword' => true,
        ]),
    ],

];
