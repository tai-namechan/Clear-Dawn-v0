<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
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
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
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
