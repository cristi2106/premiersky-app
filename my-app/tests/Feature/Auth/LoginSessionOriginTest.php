<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Regression cover for the "wrong password logs me in" report.
 *
 * Two defects combined to produce it:
 *  1. AppServiceProvider pinned every generated URL to a hardcoded Codespaces
 *     origin, so a successful login redirected the browser to a *different*
 *     origin than the one holding its new session cookie -- the login looked
 *     like it silently failed.
 *  2. POST /login sat behind the 'guest' middleware, so the next submit from
 *     that (actually signed-in) browser was redirected to the dashboard
 *     without Auth::attempt() ever running -- any password appeared to work.
 */
class LoginSessionOriginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'CorrectHorse#2026';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');
    }

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'pilot@premiersky.test',
            'password' => bcrypt(self::PASSWORD),
        ]);
    }

    public function test_correct_password_logs_in_on_the_first_try_every_time(): void
    {
        $user = $this->user();

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => self::PASSWORD,
            ])->assertRedirect(route('dashboard', absolute: false));

            $this->assertAuthenticatedAs($user);

            $this->post('/logout');
            $this->assertGuest();
        }
    }

    public function test_wrong_password_never_logs_in_no_matter_how_many_attempts(): void
    {
        $user = $this->user();

        for ($i = 1; $i <= 4; $i++) {
            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => "wrong-password-{$i}",
            ]);

            $response->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->assertNotEquals(
                route('dashboard', absolute: false),
                $response->headers->get('Location'),
                "attempt {$i} must not be sent to the dashboard"
            );
        }
    }

    public function test_wrong_password_does_not_ride_an_existing_session_to_the_dashboard(): void
    {
        $user = $this->user();

        // A genuine, valid session already in the browser.
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->assertAuthenticatedAs($user);

        // The exact reported symptom: a second submit with a bad password.
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'OBVIOUSLY-WRONG',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNotEquals(
            route('dashboard', absolute: false),
            $response->headers->get('Location')
        );
    }

    public function test_unknown_account_cannot_ride_an_existing_session(): void
    {
        $user = $this->user();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->assertAuthenticatedAs($user);

        $this->post('/login', [
            'email' => 'attacker@evil.example',
            'password' => 'hunter2',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_redirect_targets_the_origin_the_browser_actually_used(): void
    {
        $user = $this->user();

        // Behind the Codespaces proxy: real origin arrives in X-Forwarded-*.
        $forwarded = $this->withServerVariables([
            'HTTP_X_FORWARDED_HOST' => 'demo-codespace-8000.app.github.dev',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'REMOTE_ADDR' => '10.0.0.5',
        ])->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

        $this->assertStringStartsWith(
            'https://demo-codespace-8000.app.github.dev',
            $forwarded->headers->get('Location'),
            'redirect must point at the forwarded public origin'
        );

        $this->post('/logout');

        // Browsing localhost directly: no forwarded headers, stay on localhost.
        $this->withServerVariables([]);
        $direct = $this->post('http://localhost:8000/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);

        $this->assertStringStartsWith(
            'http://localhost:8000',
            $direct->headers->get('Location'),
            'a direct localhost request must not be bounced to another origin'
        );
    }
}
