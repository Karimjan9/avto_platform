<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_issues_a_hashed_mobile_token_and_normalizes_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Seller', 'email' => 'SELLER@EXAMPLE.COM', 'password' => 'Password123',
            'password_confirmation' => 'Password123', 'device_name' => 'Flutter Android',
        ])->assertCreated()->assertJsonPath('user.email', 'seller@example.com')->assertJsonPath('user.is_admin', false);
        $token = $response->json('token');
        $this->assertNotNull(PersonalAccessToken::findToken($token));
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $token]);
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonMissingPath('data.password');
    }

    public function test_public_registration_cannot_grant_admin_access(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Seller', 'email' => 'seller@example.com', 'password' => 'Password123',
            'password_confirmation' => 'Password123', 'device_name' => 'Phone', 'is_admin' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_admin');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_logout_and_independent_devices_work_with_real_bearer_tokens(): void
    {
        $user = User::factory()->create();
        $other = $user->createToken('Other phone')->plainTextToken;
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'Android',
        ])->assertOk()->json('token');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertNull(PersonalAccessToken::findToken($token));
        $this->assertNotNull(PersonalAccessToken::findToken($other));
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_bad_password_and_malformed_email_are_validation_errors(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'bad', 'device_name' => 'Phone'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/auth/login', ['email' => ['bad'], 'password' => 'bad', 'device_name' => 'Phone'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_guests_receive_json_401_without_an_accept_header(): void
    {
        $this->get('/api/v1/me')->assertUnauthorized()->assertHeader('content-type', 'application/json');
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $token = User::factory()->create()->createToken('Phone');
        $token->accessToken->forceFill(['created_at' => now()->subDays(31)])->save();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.com', 'password' => 'bad', 'device_name' => 'Phone'])
                ->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.com', 'password' => 'bad', 'device_name' => 'Phone'])
            ->assertTooManyRequests();
    }
}
