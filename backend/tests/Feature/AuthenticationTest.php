<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_log_out(): void
    {
        $this->postJson('/logout')->assertUnauthorized();
    }

    public function test_invalid_credentials_do_not_authenticate(): void
    {
        $user = User::factory()->create(['password' => 'test-password']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->postJson('/register', [])->assertNotFound();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_is_rate_limited(): void
    {
        // Authentication state can persist between requests in this test process.
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $this->assertGuest();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'nobody@example.test', 'password' => 'incorrect'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');
            $this->assertGuest();
        }
        $this->postJson('/login', ['email' => 'nobody@example.test', 'password' => 'incorrect'])->assertTooManyRequests();
    }

    public function test_logged_in_user_can_log_out(): void
    {
        $user = User::factory()->create(['password' => 'test-password']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'test-password'])->assertOk();
        $this->postJson('/logout')->assertNoContent();
        $this->assertGuest();
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'test-password']);

        $this->postJson('/login', ['email' => $user->email, 'password' => 'test-password'])
            ->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonMissingPath('user.password');
        $this->assertAuthenticatedAs($user);
    }
}
