<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Dawn-Register-2026!',
            'password_confirmation' => 'Dawn-Register-2026!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function weakPasswordProvider(): array
    {
        return [
            'too short' => ['Dawn-26!'],
            'no uppercase' => ['dawn-register-2026!'],
            'no lowercase' => ['DAWN-REGISTER-2026!'],
            'no number' => ['Dawn-Register-Now!'],
            'no symbol' => ['DawnRegister2026'],
            'starter kit default' => ['password'],
        ];
    }

    #[DataProvider('weakPasswordProvider')]
    public function test_registration_rejects_passwords_that_break_the_policy(string $password): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'weak-password@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_registration_rejects_a_leaked_password(): void
    {
        $this->pwnedPasswords = ['Dawn-Leaked-2026!'];

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'leaked-password@example.com',
            'password' => 'Dawn-Leaked-2026!',
            'password_confirmation' => 'Dawn-Leaked-2026!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'password' => 'このパスワードは情報漏洩で確認されています。別のパスワードを指定してください。',
        ]);
        $this->assertGuest();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function environmentProvider(): array
    {
        return [
            'local' => ['local'],
            'staging' => ['staging'],
            'production' => ['production'],
        ];
    }

    #[DataProvider('environmentProvider')]
    public function test_password_policy_is_the_same_in_every_environment(string $environment): void
    {
        $this->app['env'] = $environment;

        $validateStrongPassword = Validator::make(['password' => 'Dawn-Register-2026!'], ['password' => Password::default()]);
        $validateWeakPassword = Validator::make(['password' => 'password'], ['password' => Password::default()]);

        $this->assertTrue($validateStrongPassword->passes());
        $this->assertTrue($validateWeakPassword->fails());
        $this->assertCount(4, $validateWeakPassword->errors()->get('password'));
    }

    public function test_password_mixed_case_error_is_japanese(): void
    {
        Password::defaults(fn (): Password => Password::min(8)->mixedCase());

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'mixed-case@example.com',
            'password' => 'alllowercase1',
            'password_confirmation' => 'alllowercase1',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'password' => 'パスワードには大文字と小文字の両方を含めてください。',
        ]);
        $this->assertGuest();
    }

    public function test_register_store_is_rate_limited_after_five_attempts_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('register.store'), [
                'name' => 'Test User',
                'email' => "throttle-test-{$i}@example.com",
                'password' => 'not-matching',
                'password_confirmation' => 'does-not-match',
            ])->assertSessionHasErrors('password');
        }

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'throttle-test-6@example.com',
            'password' => 'not-matching',
            'password_confirmation' => 'does-not-match',
        ])->assertStatus(429);
    }
}
