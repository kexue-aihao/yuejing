<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    public function test_successful_password_reset_revokes_other_database_sessions_and_keeps_reset_session(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.test',
            'password' => 'old-password',
        ]);
        $otherUser = User::factory()->create();
        $oldSessionId = 'browser-a-session';
        $otherSessionId = 'other-user-session';

        $this->insertSession($oldSessionId, $user->id);
        $this->insertSession($otherSessionId, $otherUser->id);

        $token = Password::createToken($user);
        $newPassword = 'New-password-123!';

        $response = $this->postJsonWithCsrf(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertOk()->assertExactJson([
            'message' => 'Password reset successfully.',
        ]);
        $this->assertDatabaseMissing('sessions', ['id' => $oldSessionId]);
        $this->assertDatabaseHas('sessions', ['id' => $otherSessionId]);

        $currentSessionId = $this->app['session']->getId();
        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId]);
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));

        $this->postJsonWithCsrf(route('login'), [
            'email' => $user->email,
            'password' => $newPassword,
        ])->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_reset_token_returns_generic_error_without_changing_password_or_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-invalid@example.test',
            'password' => 'old-password',
        ]);
        $oldSessionId = 'browser-a-invalid-session';
        $this->insertSession($oldSessionId, $user->id);
        $invalidToken = Str::random(64);

        $response = $this->postJsonWithCsrf(route('password.update'), [
            'token' => $invalidToken,
            'email' => $user->email,
            'password' => 'New-password-123!',
            'password_confirmation' => 'New-password-123!',
        ]);

        $response->assertStatus(422)->assertExactJson([
            'message' => 'The password reset link is invalid or expired.',
        ]);
        $this->assertStringNotContainsString($user->email, $response->getContent());
        $this->assertStringNotContainsString($invalidToken, $response->getContent());
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
        $this->assertDatabaseHas('sessions', ['id' => $oldSessionId]);
    }

    private function insertSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'security-test',
            'payload' => base64_encode(''),
            'last_activity' => now()->timestamp,
        ]);
    }
}