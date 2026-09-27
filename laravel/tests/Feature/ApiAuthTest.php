<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Token sign-in for the native shell and handheld gate scanners. Tokens are
 * Sanctum personal access tokens, stored in personal_access_tokens.
 */
class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
    }

    public function test_a_device_signs_in_uses_its_token_and_signs_out(): void
    {
        $user = $this->member();

        $token = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
            'device_name' => 'Gatehouse scanner 1',
        ])->assertOk()->json('token');

        $this->assertIsString($token);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'Gatehouse scanner 1']);

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_a_wrong_password_issues_no_token(): void
    {
        $user = $this->member();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
            'device_name' => 'Gatehouse scanner 1',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_request_without_a_token_is_refused(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
